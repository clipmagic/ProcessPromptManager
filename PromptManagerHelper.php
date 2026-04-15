<?php declare(strict_types=1);

namespace ProcessWire;

/**
 * Helper for field selection and export payloads.
 *
 * @author ProcessWire AI Workflow, 2026
 * @license MIT
 */
class PromptManagerHelper extends Wire {

  protected ?Wire $pageReferenceOptionValueProvider = null;

  protected array $excludedFieldNames = [
    'id',
    'name',
    'status',
    'sort',
    'created',
    'modified',
    'published',
    'created_users_id',
    'modified_users_id',
  ];

  protected array $excludedFieldtypeClasses = [
    'FieldtypeCache',
    'FieldtypeComments',
    'FieldtypeFieldsetOpen',
    'FieldtypeFieldsetClose',
    'FieldtypeFieldsetTabOpen',
    'FieldtypeFieldsetGroup',
    'FieldtypeModule',
    'FieldtypePageTable',
    'FieldtypePassword',
    'FieldtypeRepeater',
    'FieldtypeRepeaterMatrix',
    'FieldtypeRuntimeMarkup',
    'FieldtypeSelector',
  ];

  public function getTemplateOptions(): array {
    $templates = $this->wire()->templates;
    $options = [];

    foreach ($templates as $template) {
      if ($template->flags & Template::flagSystem) continue;
      if ($template->name === 'admin') continue;

      $options[$template->name] = $template->name;
    }

    ksort($options);
    return $options;
  }

  public function setPageReferenceOptionValueProvider(Wire $provider): void {
    $this->pageReferenceOptionValueProvider = $provider;
  }

  public function getSelectableFieldsForTemplate(string $templateName): array {
    $template = $this->wire()->templates->get($templateName);
    if (!$template || !$template->id || !$template->fieldgroup) return [];

    $options = [];
    foreach ($template->fieldgroup as $field) {
      if (!$field instanceof Field) continue;
      if (!$this->isSelectableField($field)) continue;

      $label = (string) $field->getLabel();
      $options[$field->name] = $label && $label !== $field->name
        ? $field->name . ' - ' . $label
        : $field->name;
    }

    return $options;
  }

  public function isSelectableField(Field $field): bool {
    // Field exclusions are deliberately centralized here for easy adjustment.
    if (in_array($field->name, $this->excludedFieldNames, true)) return false;
    if (strpos($field->name, '_') === 0) return false;
    if (strpos($field->name, 'pw_') === 0) return false;

    $typeClass = $field->type ? $field->type->className() : '';
    if (in_array($typeClass, $this->excludedFieldtypeClasses, true)) return false;

    return true;
  }

  public function normalizeFieldNames(array $fieldNames, string $templateName): array {
    $sanitizer = $this->wire()->sanitizer;
    $allowed = array_keys($this->getSelectableFieldsForTemplate($templateName));
    $selected = [];

    foreach ($fieldNames as $fieldName) {
      $fieldName = $sanitizer->fieldName((string) $fieldName);
      if (!$fieldName) continue;
      if (!in_array($fieldName, $allowed, true)) continue;

      $selected[] = $fieldName;
    }

    return array_values(array_unique($selected));
  }

  public function decodeFieldNames(string $fieldNamesJson): array {
    if (!$fieldNamesJson) return [];

    $decoded = json_decode($fieldNamesJson, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
  }

  public function payloadFromRecord(array $record): array {
    return $this->buildPayload(
      (string) ($record['template_name'] ?? ''),
      $this->decodeFieldNames((string) ($record['field_names'] ?? ''))
    );
  }

  public function buildMarkdownPrompt(
    string $name,
    string $key,
    string $templateName,
    array $fields,
    string $prompt,
    string $notes = ''
  ): string {
    $lines = [
      '# Instructions',
      '',
      trim($prompt) ?: '_No prompt instructions entered yet._',
      '',
      '## Field data to provide (must match JSON field names exactly)',
      '',
      'Required fields must always be included. Do not invent values if the correct value is unknown. If a required field cannot be confidently populated, leave it empty.',
      '',
    ];

    $fieldDefinitions = $this->getFieldDefinitions($templateName, $fields, $key);
    if (!$fieldDefinitions) {
      $lines[] = '_No fields selected._';
    } else {
      foreach ($fieldDefinitions as $field) {
        $lines[] = '- `' . $field['name'] . '` - ' . $field['label'] . ' (' . $field['type'] . ', ' . $field['required'] . ')';
      }
    }

    return implode("\n", $lines);
  }

  public function buildPayload(
    string $templateName,
    array $fields
  ): array {
    return [
      'fields' => $this->normalizeFieldNames($fields, $templateName),
    ];
  }

  public function getFieldDefinitions(string $templateName, array $fieldNames, string $promptKey = ''): array {
    $template = $this->wire()->templates->get($templateName);
    if (!$template || !$template->id || !$template->fieldgroup) return [];

    $definitions = [];
    $allowed = $this->normalizeFieldNames($fieldNames, $templateName);
    foreach ($allowed as $fieldName) {
      $field = $template->fieldgroup->getFieldContext($fieldName);
      if (!$field instanceof Field) continue;

      $definitions[] = [
        'name' => $field->name,
        'label' => (string) $field->getLabel(),
        'type' => $this->fieldDataType($field, $promptKey, $template),
        'required' => $this->fieldIsRequired($field) ? 'required' : 'optional',
      ];
    }

    return $definitions;
  }

  public function fieldDataType(Field $field, string $promptKey = '', ?Template $template = null): string {
    $typeClass = $field->type ? $field->type->className() : '';
    if (in_array($typeClass, [
      'FieldtypePageTitle',
      'FieldtypePageTitleLanguage',
      'FieldtypeText',
      'FieldtypeTextLanguage',
    ], true)) {
      return 'short text' . $this->fieldLengthHint($field);
    }

    if (in_array($typeClass, [
      'FieldtypeTextarea',
      'FieldtypeTextareaLanguage',
    ], true)) {
      return $this->fieldTextareaHint($field) . $this->fieldLengthHint($field);
    }

    if ($typeClass === 'FieldtypeEmail') {
      return 'valid email address';
    }

    if ($typeClass === 'FieldtypeInteger') {
      return 'integer' . $this->fieldNumberHint($field);
    }

    if ($typeClass === 'FieldtypeFloat') {
      return 'number' . $this->fieldNumberHint($field);
    }

    if ($typeClass === 'FieldtypeDecimal') {
      return $this->fieldDecimalHint($field);
    }

    if ($typeClass === 'FieldtypeDatetime') {
      return $this->fieldDatetimeHint($field);
    }

    if ($typeClass === 'FieldtypeURL') {
      return $this->fieldUrlHint($field);
    }

    if ($typeClass === 'FieldtypeOptions') {
      return $this->fieldOptionsHint($field, $promptKey);
    }

    if ($typeClass === 'FieldtypePage') {
      return $this->fieldPageReferenceHint($field, $promptKey, $template);
    }

    if ($typeClass === 'FieldtypeCheckbox') {
      return $this->fieldCheckboxHint();
    }

    if ($typeClass === 'FieldtypeToggle') {
      return $this->fieldToggleHint($field);
    }

    if ($typeClass === 'FieldtypeFile') {
      return 'properly attributed file source URL only; do not scrape, download, upload, or generate the file';
    }

    if ($typeClass === 'FieldtypeImage') {
      return 'properly attributed image source URL only; do not scrape, download, upload, or generate the image';
    }

    if (strpos($typeClass, 'Fieldtype') === 0) return strtolower(substr($typeClass, 9));

    return 'text';
  }

  protected function fieldIsRequired(Field $field): bool {
    return (bool) $field->get('required');
  }

  protected function fieldLengthHint(Field $field): string {
    $parts = [];
    $minlength = (int) $field->get('minlength');
    $maxlength = (int) $field->get('maxlength');

    if ($minlength > 0) $parts[] = 'min ' . $minlength . ' chars';
    if ($maxlength > 0) $parts[] = 'max ' . $maxlength . ' chars';

    return $parts ? ', ' . implode(', ', $parts) : '';
  }

  protected function fieldTextareaHint(Field $field): string {
    $inputfieldClass = $this->fieldConfigValue($field, 'inputfieldClass');
    if ($inputfieldClass === '' || $inputfieldClass === 'InputfieldTextarea') return 'long text';

    return 'long text, HTML/formatting allowed';
  }

  protected function fieldNumberHint(Field $field): string {
    $parts = [];
    $min = $this->fieldConfigValue($field, 'min');
    $max = $this->fieldConfigValue($field, 'max');

    if ($min !== '' && $max !== '') {
      $parts[] = 'between ' . $min . ' and ' . $max;
    } else if ($min !== '') {
      $parts[] = 'minimum ' . $min;
    } else if ($max !== '') {
      $parts[] = 'maximum ' . $max;
    }

    return $parts ? ', ' . implode(', ', $parts) : '';
  }

  protected function fieldDecimalHint(Field $field): string {
    $digits = $field->get('digits');
    $precision = $field->get('precision');
    if ($digits < 1) $digits = 10;
    if ($precision === null || $precision < 0) $precision = 2;
    $digits = (int) $digits;
    $precision = (int) $precision;

    $parts = ['decimal number'];
    if ($precision > 0) {
      $parts[] = $precision . ' decimal places';
    } else {
      $parts[] = 'whole number';
    }

    $parts[] = 'up to ' . $digits . ' total digits';

    return implode(', ', $parts);
  }

  protected function fieldDatetimeHint(Field $field): string {
    $inputType = $this->fieldConfigValue($field, 'inputType');
    if ($inputType === '') $inputType = 'text';

    if ($inputType === 'html') {
      return $this->fieldHtmlDatetimeHint($field);
    }

    $dateFormat = $this->fieldConfigValue($field, 'dateInputFormat');
    $timeFormat = $this->fieldConfigValue($field, 'timeInputFormat');
    if ($dateFormat === '' && $timeFormat === '') $dateFormat = 'Y-m-d';

    if ($dateFormat !== '' && $timeFormat !== '') return 'date and time, format ' . $dateFormat . ' ' . $timeFormat;
    if ($dateFormat !== '') return 'date, format ' . $dateFormat;

    return 'time, format ' . $timeFormat;
  }

  protected function fieldHtmlDatetimeHint(Field $field): string {
    $htmlType = $this->fieldConfigValue($field, 'htmlType');
    if ($htmlType === '') $htmlType = 'date';

    if ($htmlType === 'time') {
      $timeStep = (int) $field->get('timeStep');
      return $timeStep > 0 && $timeStep < 60 ? 'time, format HH:MM:SS' : 'time, format HH:MM';
    }

    if ($htmlType === 'datetime') {
      $timeStep = (int) $field->get('timeStep');
      return $timeStep > 0 && $timeStep < 60
        ? 'date and time, format YYYY-MM-DD HH:MM:SS'
        : 'date and time, format YYYY-MM-DD HH:MM';
    }

    return 'date, format YYYY-MM-DD';
  }

  protected function fieldUrlHint(Field $field): string {
    $parts = [(int) $field->get('noRelative')
      ? 'valid absolute URL'
      : 'valid URL; use absolute URLs for external sites and root-relative URLs for ' . $this->fieldUrlHostLabel()];
    if ((int) $field->get('allowIDN')) $parts[] = 'internationalized domain names allowed';

    return implode(', ', $parts);
  }

  protected function fieldUrlHostLabel(): string {
    $host = trim((string) $this->wire()->config->httpHost);

    return $host !== '' ? $host : 'this site';
  }

  protected function fieldOptionsHint(Field $field, string $promptKey = ''): string {
    $options = $this->fieldOptionChoices($field);
    $multiple = $this->fieldOptionsAllowMultiple($field);
    if ($promptKey !== '') {
      $prefix = $multiple ? 'choose one or more from: ' : 'choose one from: ';
      return $prefix . $this->fieldOptionsFilename($promptKey, $field->name);
    }

    $prefix = $multiple ? 'choose one or more of: ' : 'choose one of: ';
    $values = array_map(fn(array $option): string => $this->quoteOptionValue($option['value']), $options);

    return $values ? $prefix . implode(', ', $values) : 'option value';
  }

  protected function fieldCheckboxHint(): string {
    return 'true or false';
  }

  protected function fieldToggleHint(Field $field): string {
    $options = $this->fieldToggleChoices($field);
    if (!$options) return 'choose one of: "yes", "no"';

    $values = array_map(fn(array $option): string => $this->fieldChoicePromptValue($option), $options);
    $hint = 'choose one of: ' . implode(', ', $values);

    if ($this->fieldToggleAllowsUnknown($field)) $hint .= '; leave empty if unknown';

    return $hint;
  }

  protected function fieldToggleLabels(Field $field): array {
    $labels = [
      'yes' => 'Yes',
      'no' => 'No',
      'other' => 'Other',
      'unknown' => 'Unknown',
    ];

    $inputfield = $this->wire()->modules->get('InputfieldToggle');
    if (!$inputfield || !method_exists($inputfield, 'getLabels')) return $labels;

    foreach ([
      'labelType',
      'yesLabel',
      'noLabel',
      'otherLabel',
      'useOther',
      'useReverse',
      'defaultOption',
    ] as $property) {
      $value = $field->get($property);
      if ($value === null || $value === '') continue;

      $inputfield->set($property, $value);
    }

    $configuredLabels = $inputfield->getLabels();
    return is_array($configuredLabels) ? array_merge($labels, $configuredLabels) : $labels;
  }

  protected function fieldToggleAllowsUnknown(Field $field): bool {
    $defaultOption = $this->fieldConfigValue($field, 'defaultOption');

    return $defaultOption === '' || $defaultOption === 'none' || (bool) (int) $field->get('useDeselect');
  }

  protected function fieldOptionChoices(Field $field): array {
    $values = [];

    try {
      $options = $field->type->getOptions($field);
    } catch (\Exception $e) {
      return [];
    }

    foreach ($options as $option) {
      $value = method_exists($option, 'getValue')
        ? trim((string) $option->getValue())
        : trim((string) $option->get('value'));
      $label = method_exists($option, 'getTitle')
        ? trim((string) $option->getTitle())
        : trim((string) $option->get('title'));
      if ($value === '') $value = $label;
      if ($label === '') $label = $value;
      if ($value === '' || $label === '') continue;

      $values[] = [
        'value' => $value,
        'label' => $label,
      ];
    }

    return $values;
  }

  protected function fieldToggleChoices(Field $field): array {
    $labels = $this->fieldToggleLabels($field);
    $keys = (int) $field->get('useReverse') ? ['no', 'yes'] : ['yes', 'no'];
    $values = [
      'yes' => 'yes',
      'no' => 'no',
      'other' => 'other',
    ];
    $choices = [];

    if ((int) $field->get('useOther')) $keys[] = 'other';

    foreach ($keys as $key) {
      $value = $values[$key] ?? '';
      $label = trim((string) ($labels[$key] ?? ''));
      if ($value === '' || $label === '') continue;

      $choices[] = [
        'value' => $value,
        'label' => $label,
      ];
    }

    return $choices;
  }

  protected function fieldChoicePromptValue(array $option): string {
    $value = (string) ($option['value'] ?? '');
    $label = trim((string) ($option['label'] ?? ''));
    $hint = $this->quoteOptionValue($value);

    if ($label !== '' && strcasecmp($label, $value) !== 0) {
      $hint .= ' (' . $label . ')';
    }

    return $hint;
  }

  protected function fieldOptionsAllowMultiple(Field $field): bool {
    $inputfieldClass = $this->fieldConfigValue($field, 'inputfieldClass') ?: 'InputfieldSelect';
    $interfaces = wireClassImplements($inputfieldClass);

    return in_array('InputfieldHasArrayValue', $interfaces, true);
  }

  protected function quoteOptionValue(string $value): string {
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""';
  }

  public function buildFieldChoiceOptionFiles(string $promptKey, string $templateName, array $fieldNames): array {
    $template = $this->wire()->templates->get($templateName);
    if (!$template || !$template->id || !$template->fieldgroup) return [];

    $files = [];
    $allowed = $this->normalizeFieldNames($fieldNames, $templateName);
    foreach ($allowed as $fieldName) {
      $field = $template->fieldgroup->getFieldContext($fieldName);
      if (!$field instanceof Field || !$field->type) continue;

      $typeClass = $field->type->className();
      if ($typeClass === 'FieldtypeOptions') {
        $choices = $this->fieldOptionChoices($field);
      } else {
        continue;
      }

      $values = array_map(fn(array $option): string => (string) $option['value'], $choices);
      $files[$this->fieldOptionsFilename($promptKey, $field->name)] = $this->payloadToJson($values) . "\n";
    }

    return $files;
  }

  public function buildPageReferenceOptionFiles(string $promptKey, string $templateName, array $fieldNames): array {
    $template = $this->wire()->templates->get($templateName);
    if (!$template || !$template->id || !$template->fieldgroup) return [];

    $files = [];
    $allowed = $this->normalizeFieldNames($fieldNames, $templateName);
    foreach ($allowed as $fieldName) {
      $field = $template->fieldgroup->getFieldContext($fieldName);
      if (!$field instanceof Field) continue;
      if (!$field->type || $field->type->className() !== 'FieldtypePage') continue;

      $values = $this->fieldPageReferenceValues($field, $template);
      if ($values === null) continue;

      $files[$this->fieldOptionsFilename($promptKey, $field->name)] = $this->payloadToJson($values) . "\n";
    }

    return $files;
  }

  protected function fieldPageReferenceHint(Field $field, string $promptKey = '', ?Template $template = null): string {
    $multiple = !$this->fieldPageReferenceIsSingle($field);
    $values = $this->fieldPageReferenceValues($field, $template);

    if ($values !== null && $promptKey !== '') {
      $prefix = $multiple ? 'choose one or more from: ' : 'choose one from: ';
      return $prefix . $this->fieldOptionsFilename($promptKey, $field->name);
    }

    $label = (string) $field->getLabel();
    if ($label === '') $label = $field->name;

    return $multiple
      ? 'choose one or more existing ' . $label . ' pages'
      : 'choose one existing ' . $label . ' page';
  }

  protected function fieldPageReferenceIsSingle(Field $field): bool {
    return (int) $field->get('derefAsPage') > 0;
  }

  protected function fieldPageReferenceValues(Field $field, ?Template $template = null): ?array {
    $selector = $this->fieldPageReferenceSelector($field);
    if ($selector === '') return null;

    try {
      $pageArray = $this->wire()->pages->find($selector);
    } catch (\Exception $e) {
      return null;
    }

    $values = [];
    $excludedRootPageIds = $this->excludedRootPageIds();
    $excludedTemplateIds = $this->excludedPageReferenceTemplateIds();
    foreach ($pageArray as $page) {
      if (!$page instanceof Page || !$page->id) continue;
      if ($this->pageIsInExcludedRoot($page, $excludedRootPageIds)) continue;
      if (in_array((int) $page->template->id, $excludedTemplateIds, true)) continue;

      $value = $this->pageReferenceOptionValue($page, $field, $template);
      if ($value === null) continue;
      if (is_string($value) && trim($value) === '') continue;
      if (is_array($value) && $value === []) continue;

      $values[] = $value;
    }

    $this->sortPageReferenceValues($values);
    return $values;
  }

  protected function pageReferenceOptionValue(Page $page, Field $field, ?Template $template = null): mixed {
    if ($this->pageReferenceOptionValueProvider) {
      return $this->pageReferenceOptionValueProvider->pageReferenceOptionValue($page, $field, $template);
    }

    return trim((string) $page->get('title|name'));
  }

  protected function sortPageReferenceValues(array &$values): void {
    usort($values, static function(mixed $a, mixed $b): int {
      return strnatcasecmp(
        self::pageReferenceSortValue($a),
        self::pageReferenceSortValue($b)
      );
    });
  }

  protected static function pageReferenceSortValue(mixed $value): string {
    if (is_array($value)) {
      foreach (['title', 'name', 'label', 'author_name'] as $key) {
        if (isset($value[$key]) && is_scalar($value[$key])) {
          return (string) $value[$key];
        }
      }
    }

    if (is_scalar($value)) return (string) $value;

    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
  }

  protected function fieldPageReferenceSelector(Field $field): string {
    if ($this->fieldConfigValue($field, 'findPagesCode') !== '') return '';

    $selector = $this->fieldConfigValue($field, 'findPagesSelector');
    if ($selector === '') $selector = $this->fieldConfigValue($field, 'findPagesSelect');
    if (strpos($selector, '=page.') !== false || strpos($selector, '=item.') !== false) return '';

    $parts = [];
    if ($selector !== '') $parts[] = $selector;

    $parentId = (int) $field->get('parent_id');
    if ($parentId > 0) {
      $parts[] = $this->fieldPageReferenceUsesRootParent($field) ? 'has_parent=' . $parentId : 'parent_id=' . $parentId;
    }

    $templateIds = FieldtypePage::getTemplateIDs($field, true);
    if ($templateIds !== '') $parts[] = 'templates_id=' . $templateIds;

    if (!$parts) return '';

    $parts[] = ((int) $field->get('allowUnpub') ? 'include=unpublished' : 'include=hidden');
    $parts[] = 'check_access=0';

    foreach ($this->excludedRootPageIds() as $pageId) {
      $parts[] = 'id!=' . $pageId;
      $parts[] = 'has_parent!=' . $pageId;
    }

    foreach ($this->excludedPageReferenceTemplateIds() as $templateId) {
      $parts[] = 'templates_id!=' . $templateId;
    }

    return implode(', ', $parts);
  }

  protected function fieldOptionsFilename(string $promptKey, string $fieldName): string {
    $sanitizer = $this->wire()->sanitizer;
    $base = $sanitizer->pageName($promptKey, true) ?: 'prompt-definition';
    $fieldName = $sanitizer->fieldName($fieldName);

    return $base . '__' . $fieldName . '.json';
  }

  protected function fieldPageReferenceUsesRootParent(Field $field): bool {
    $inputfieldClass = $this->fieldConfigValue($field, 'inputfield') ?: 'InputfieldSelect';
    $inputfieldClass = ltrim($inputfieldClass, '_');

    return in_array('InputfieldPageListSelection', wireClassImplements($inputfieldClass), true);
  }

  protected function excludedRootPageIds(): array {
    $config = $this->wire()->config;
    $modules = $this->wire()->modules;
    $pages = $this->wire()->pages;
    $templates = $this->wire()->templates;

    $pageIds = [
      (int) $config->adminRootPageID,
      (int) $config->trashPageID,
      (int) $config->http404PageID,
    ];

    if ($modules->isInstalled('FieldtypeRepeater')) {
      $fieldtypeRepeater = $modules->get('FieldtypeRepeater');
      if ($fieldtypeRepeater) $pageIds[] = (int) $fieldtypeRepeater->get('repeatersRootPageID');
    }

    $template = $templates->get('rockpagebuilder_datapage');
    if ($template && $template->id) {
      foreach ($pages->find('templates_id=' . (int) $template->id . ', include=all, check_access=0') as $page) {
        if ($page instanceof Page && $page->id) $pageIds[] = (int) $page->id;
      }
    }

    return array_values(array_filter(array_unique($pageIds)));
  }

  protected function excludedPageReferenceTemplateIds(): array {
    $templates = $this->wire()->templates;
    $templateIds = [];
    foreach ($templates as $template) {
      if ($template->name === 'rockpagebuilder_datapage' || strpos($template->name, 'repeater_') === 0) {
        $templateIds[] = (int) $template->id;
      }
    }

    return array_values(array_unique($templateIds));
  }

  protected function pageIsInExcludedRoot(Page $page, array $rootPageIds): bool {
    foreach ($rootPageIds as $rootPageId) {
      if ($page->id === $rootPageId || $page->parents()->has($rootPageId)) return true;
    }

    return false;
  }

  protected function fieldConfigValue(Field $field, string $name): string {
    $value = $field->get($name);
    if ($value === null) return '';

    return trim((string) $value);
  }

  public function payloadToJson(array $payload): string {
    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
  }
}
