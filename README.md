# ProcessPromptManager

ProcessPromptManager is a ProcessWire admin module for building site-aware prompt definition exports for external AI agents.

It lets an administrator choose a ProcessWire template, select the fields an agent is expected to provide, add human-written instructions, and export a zip containing a markdown prompt plus small JSON files that describe the expected field payload.

The module does not call an AI service. It prepares prompt and field-definition files that can be used by a separate agent, workflow, or integration.

## Status

Current version: `0.0.7Beta`

This is a beta release. Test it against your own templates, fields, exports, permissions, and receiving workflow before using it on a production site.

## Requirements

- ProcessWire 3.0.0 or newer
- PHP with `ZipArchive` enabled for exports
- Superuser access for installation and configuration

## Installation

1. Get the module using one of these options:

   Download the repository main zip:

   ```text
   https://github.com/clipmagic/ProcessPromptManager/archive/refs/heads/main.zip
   ```

   Or clone the repository:

   ```text
   https://github.com/clipmagic/ProcessPromptManager
   ```

2. Copy the module folder to:

   ```text
   /site/modules/ProcessPromptManager/
   ```

3. In the ProcessWire admin, go to **Modules > Refresh**.
4. Install **Prompt Manager**.
5. Confirm the `prompt-manager` permission exists.
6. Assign the `prompt-manager` permission to any role that should manage prompt definitions.

After installation, the admin page is available under:

```text
Setup > Prompt Manager
```

## What It Creates

Prompt definitions are stored in a dedicated database table:

```text
prompt_manager_definitions
```

Each prompt definition contains:

- Name
- Prompt key
- Optional endpoint URL for the receiving workflow. Same-site endpoints should be stored as root-relative URLs, for example `/api/endpoint/`, so prompt definitions remain portable between environments.
- Template
- Selected template fields
- Prompt instructions
- Internal notes for hints, tips, and reminders. These notes are not sent to the agent.
- Last exported date, set automatically when export files are generated for a saved prompt definition.

## Basic Workflow

1. Go to **Setup > Prompt Manager**.
2. Click **Add prompt definition**.
3. Enter a name.
4. Optionally enter the endpoint URL for the receiving workflow. Use a root-relative URL for this site, for example `/api/endpoint/`.
5. Choose a ProcessWire template.
6. Select the fields the external agent should provide.
7. Write the prompt instructions.
8. Choose whether sidecar JSON should be included inline in the generated prompt.
9. Add any internal notes for your own hints, tips, and reminders.
10. Preview the generated output.
11. Save the definition.
12. Export the zip file.

The prompt definition list includes a **Last exported** column. The add/edit screen also shows the last exported status near the export button. Unsaved prompts can be exported for preview, but export tracking starts after the prompt definition has been saved.

## Export Files

An export zip can contain:

- `{prompt_key}.md`
- `{prompt_key}.json`
- `{prompt_key}__{field_name}.json` sidecar files for enumerable Page Reference fields
- `{prompt_key}__{field_name}.json` sidecar files for Select Options fields

Sidecar JSON is exported as separate files by default. If **Include sidecar JSON in prompt** is checked, the markdown prompt also includes the sidecar JSON inline. This is useful for agents or workflows that cannot upload or attach sidecar files, but it can increase token usage.

Example:

```text
blog_article.md
blog_article.json
blog_article__pg_person.json
blog_article__blog_tags.json
```

## Main JSON Contract

The main JSON export intentionally contains field names only:

```json
{
  "fields": [
    "title",
    "summary",
    "body"
  ]
}
```

This contract is deliberately small. Field labels, field guidance, required status, and instructions live in the markdown file.

## Markdown Prompt

The markdown export includes:

- The human-written prompt instructions
- A list of selected fields
- Field labels
- Required or optional status
- Field data guidance

The generated guidance is based on ProcessWire field types and field context where available.

If an endpoint URL is entered, the markdown export includes a **Delivery** section telling the agent where to send the completed JSON payload. Same-site endpoints are stored as root-relative paths and expanded against the current site URL during markdown generation. The endpoint URL is not included in the main JSON export.

Notes entered in the admin form are for internal hints, tips, and reminders only. They are not included in the generated markdown prompt and are not sent to the agent.

**Tip**: include a clear stop cue in your own prompt instructions. Tell the agent what to return when required conditions cannot be met, for example when source data is missing, a required field cannot be populated confidently, or no valid Page Reference option applies.

Example prompt instruction:

```text
Write a concise service page for the selected template fields. Use only information provided in this prompt and its source data. If a required field cannot be completed confidently, return STOP with a short reason instead of inventing missing details.
```

## Sidecar JSON Files

Some field types need a list of allowed values. These lists are exported as separate sidecar JSON files.

### Page Reference Fields

Enumerable Page Reference fields are exported as JSON objects that identify the field and list the valid values. Default options include the referenced page `id` and `title`.

Default example:

```json
{
  "field": "blog_tags",
  "value_type": "page_id",
  "return": "Return id values from values for this field only.",
  "values": [
    {
      "id": 123,
      "title": "Diabetic foot care"
    },
    {
      "id": 124,
      "title": "Sports injuries"
    }
  ]
}
```

Page Reference option exports exclude internal/system pages including:

- Admin branch
- Trash branch
- Configured 404 page
- RockPageBuilder datapages
- Repeater pages
- RepeaterMatrix pages

Dynamic Page Reference selectors using `page.` / `item.` and `findPagesCode` are not enumerated.

### Select Options Fields

Select Options fields are exported as JSON objects that identify the field and list the exact valid values.

Example:

```json
{
  "field": "rating",
  "value_type": "option_value",
  "return": "Return exact option values from values for this field only.",
  "values": [
    "good",
    "average",
    "poor"
  ]
}
```

Option labels are intentionally not included in Select Options sidecar files.

## Field Guidance Notes

This version handles ProcessWire core fieldtypes. Custom or third-party fieldtypes are not guaranteed to produce useful guidance or sidecar exports without testing.

The generated markdown includes specific guidance for common field types:

- Text fields: short text guidance
- Textarea fields: long text or HTML guidance where appropriate
- Email fields: valid email address guidance
- Integer, Float, Decimal fields: number guidance and precision hints where available
- Datetime fields: date/time format guidance
- URL fields: absolute external URL or root-relative internal URL guidance where appropriate
- Checkbox fields: `true or false`
- Toggle fields: inline configured choices
- File and Image fields: attributed source URL references only

For File and Image fields, the generated prompt tells the agent not to scrape, download, upload, or generate files.

If a custom fieldtype needs special handling, please discuss it in the ProcessWire support forum or submit a pull request.

## Customising Page Reference Sidecar Values

The module exposes a hookable method for filtering sidecar options before they are exported:

```php
ProcessPromptManager::allowSidecarOption(Field $field, mixed $option, ?Template $template = null)
```

The `$option` argument depends on the sidecar field type:

- Page Reference fields receive a `Page`.
- Select Options fields receive the option data used by Prompt Manager.

Return `false` to exclude the option from the sidecar.

Example:

```php
$wire->addHookAfter('ProcessPromptManager::allowSidecarOption', function(HookEvent $event) {
    $field = $event->arguments(0);
    $option = $event->arguments(1);
    $template = $event->arguments(2);

    if (!$field instanceof Field || !$template instanceof Template) return;
    if ($template->name !== 'blog-article' || $field->name !== 'pg_person') return;
    if (!$option instanceof Page) return;

    $event->return = $option->template->name === 'team-member'
        && (int) $option->staff_category->id === 1;
});
```

The module exposes a hookable method for Page Reference option values:

```php
ProcessPromptManager::pageReferenceOptionValue(Page $page, Field $field, ?Template $template = null)
```

By default, Page Reference sidecar options include the page `id` and `title`. The `id` is the value the agent should return for the Page Reference field.

Example:

```php
$wire->addHookAfter('ProcessPromptManager::pageReferenceOptionValue', function(HookEvent $event) {
    $page = $event->arguments(0);
    $field = $event->arguments(1);

    if ($field->name !== 'pg_person') return;

    $event->return = [
        'id' => (int) $page->id,
        'author_name' => trim($page->title . ' ' . $page->lastname),
    ];
});
```
Explain to the agent how to handle the data in the custom prompt. For example:

```
Select a valid person from the accompanying `pg_person` sidecar data. 
Use the `id` value for the `pg_person` field in the JSON payload. 
You may use the matching `author_name` anywhere appropriate in the article content. 
Do not invent people.
```
This hook is currently limited to Page Reference sidecar exports.

For taxonomy-style references, a useful ProcessWire pattern is to add a `synonyms` or `keywords` field to the referenced pages and expose that data in the sidecar. This lets the agent match source text to valid site taxonomy without hardcoding those terms in the prompt.

Example:

```php
$wire->addHookAfter('ProcessPromptManager::pageReferenceOptionValue', function(HookEvent $event) {
    $page = $event->arguments(0);
    $field = $event->arguments(1);

    if (!in_array($field->name, ['blog_tags', 'pg_services', 'pg_conditions'], true)) return;

    $synonyms = array_filter(array_map('trim', explode(',', (string) $page->synonyms)));

    $event->return = [
        'id' => (int) $page->id,
        'title' => (string) $page->title,
        'synonyms' => array_values($synonyms),
    ];
});
```

Prompt wording can then stay generic:

```
For taxonomy Page Reference fields, choose sidecar id values when the source directly matches the option title or synonyms. Do not choose options without evidence in the source.
```

## What This Module Does Not Do

- It does not call OpenAI, Anthropic, or any other AI API.
- It does not create or update site pages from AI output.
- It does not manage API credentials.
- It does not store prompt definitions as hidden ProcessWire pages.
- It does not export a full schema document.
- It does not enumerate dynamic Page Reference fields that depend on runtime page context.

## What To Build Next

After exporting a prompt definition, you still need a receiving workflow for the generated data.

A typical implementation looks like this:

1. Give the exported markdown prompt and JSON files to your external agent or automation tool.
2. Configure the agent to return data that matches the selected field names.
3. Create an endpoint in your ProcessWire site to receive the generated JSON payload.
4. Authenticate the request.
5. Sanitize and validate every incoming value with ProcessWire APIs.
6. Resolve Page Reference fields against allowed pages.
7. Create, update, save as draft, or reject the page according to your own workflow.
8. Log the result and return a clear response to the caller.

For example, a site using the AppApi module might add a small route that accepts a `POST` request and passes the decoded payload to a handler class:

```php
$routes = [
    'agent' => [
        'v1' => [
            'draft' => [
                ['OPTIONS', '', ['POST']],
                ['POST', '', AgentDrafts::class, 'createDraft'],
            ],
        ],
    ],
];
```

The handler should treat the agent output as untrusted input:

```php
class AgentDrafts
{
    public static function createDraft($data): array
    {
        $sanitizer = wire('sanitizer');

        $title = $sanitizer->text((string) ($data->title ?? ''));
        if ($title === '') {
            return [
                'created' => false,
                'errors' => [
                    'title' => 'Title is required.',
                ],
            ];
        }

        $page = wire('pages')->newPage([
            'template' => 'example-template',
            'parent' => wire('pages')->get('/example-parent/'),
            'name' => $sanitizer->pageName($title, true),
        ]);

        $page->of(false);
        $page->title = $title;
        $page->addStatus(Page::statusUnpublished);
        wire('pages')->save($page);

        return [
            'created' => true,
            'id' => (int) $page->id,
            'status' => 'unpublished',
        ];
    }
}
```

This is only an example. The endpoint, authentication method, field mapping, validation rules, and save workflow should be designed for your own site.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).
