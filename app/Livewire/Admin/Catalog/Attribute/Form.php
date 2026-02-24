<?php

namespace App\Livewire\Admin\Catalog\Attribute;

use App\Models\Catalog\Attribute\Attribute;
use App\Models\Catalog\Attribute\AttributeTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $attributeId = null;

    public array $form = [
        'code' => '',
        'group_code' => '',
        'type' => Attribute::TYPE_SELECT,
        'is_active' => true,
        'sort_order' => 0,
        'payload_text' => '',
        'locale' => 'en',
        'group_name' => '',
        'name' => '',
        'slug' => '',
        'description' => '',
        'translation_payload_text' => '',
    ];

    public function mount(?int $attributeId = null): void
    {
        $this->form['locale'] = (string) (request()->query('locale') ?: config('app.locale', 'en'));

        if ($attributeId) {
            $this->attributeId = $attributeId;
            $this->loadAttribute();
        }
    }

    public function updatedFormLocale(): void
    {
        $this->loadTranslationForLocale();
    }

    public function generateSlug(): void
    {
        $name = trim((string) $this->form['name']);
        if ($name !== '') {
            $this->form['slug'] = Str::slug($name);
        }
    }

    public function save()
    {
        $validated = $this->validate($this->rules());
        $wasEditing = (bool) $this->attributeId;

        $payload = $this->decodeJsonField('form.payload_text');
        if ($payload === false) {
            return null;
        }

        $translationPayload = $this->decodeJsonField('form.translation_payload_text');
        if ($translationPayload === false) {
            return null;
        }

        $userId = auth()->id();

        DB::transaction(function () use ($validated, $payload, $translationPayload, $userId, $wasEditing): void {
            $attributeData = [
                'code' => trim((string) $validated['form']['code']),
                'group_code' => trim((string) $validated['form']['group_code']),
                'type' => (string) $validated['form']['type'],
                'is_active' => (bool) $validated['form']['is_active'],
                'sort_order' => (int) $validated['form']['sort_order'],
                'payload' => $payload,
                'updated_by' => $userId,
            ];

            if ($this->attributeId) {
                $attribute = Attribute::query()->findOrFail($this->attributeId);
                $attribute->fill($attributeData)->save();
            } else {
                $attribute = Attribute::query()->create($attributeData + ['created_by' => $userId]);
                $this->attributeId = $attribute->id;
            }

            $attribute->translations()->updateOrCreate(
                ['locale' => $validated['form']['locale']],
                [
                    'group_name' => $validated['form']['group_name'],
                    'name' => $validated['form']['name'],
                    'slug' => $validated['form']['slug'],
                    'description' => $validated['form']['description'] ?: null,
                    'payload' => $translationPayload,
                ]
            );

            activity('catalog_attributes')
                ->performedOn($attribute)
                ->causedBy(auth()->user())
                ->event($wasEditing ? 'updated' : 'created')
                ->withProperties([
                    'locale' => $validated['form']['locale'],
                    'group_code' => $validated['form']['group_code'],
                    'group_name' => $validated['form']['group_name'],
                    'slug' => $validated['form']['slug'],
                ])
                ->log('Attribute saved');
        });

        $message = $wasEditing ? __('Attribute updated.') : __('Attribute created.');

        return redirect()
            ->route('admin.attributes', ['locale' => $this->form['locale']])
            ->with('notify', [
                'type' => 'success',
                'message' => $message,
            ]);
    }

    public function backToList()
    {
        return redirect()->route('admin.attributes', ['locale' => $this->form['locale']]);
    }

    /**
     * @return array<int, string>
     */
    public function getTypeOptionsProperty(): array
    {
        return Attribute::availableTypes();
    }

    public function render()
    {
        return view('livewire.admin.catalog.attribute.form', [
            'isEdit' => (bool) $this->attributeId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.code' => [
                'required',
                'string',
                'max:120',
                Rule::unique('catalog_attributes', 'code')->ignore($this->attributeId),
            ],
            'form.group_code' => ['required', 'string', 'max:120'],
            'form.type' => ['required', Rule::in(Attribute::availableTypes())],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
            'form.payload_text' => ['nullable', 'string'],

            'form.locale' => ['required', 'string', 'max:12'],
            'form.group_name' => ['required', 'string', 'max:255'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.slug' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('catalog_attribute_translations', 'slug')
                    ->where(fn ($q) => $q->where('locale', $this->form['locale']))
                    ->ignore($this->attributeId, 'attribute_id'),
            ],
            'form.description' => ['nullable', 'string'],
            'form.translation_payload_text' => ['nullable', 'string'],
        ];
    }

    private function loadAttribute(): void
    {
        if (!$this->attributeId) {
            return;
        }

        $attribute = Attribute::query()
            ->with('translations')
            ->findOrFail($this->attributeId);

        $preferredLocale = $this->form['locale'] ?: config('app.locale', 'en');
        $translation = $attribute->translations->firstWhere('locale', $preferredLocale)
            ?? $attribute->translations->firstWhere('locale', config('app.locale', 'en'))
            ?? $attribute->translations->first();

        $this->form['code'] = $attribute->code;
        $this->form['group_code'] = $attribute->group_code;
        $this->form['type'] = $attribute->type;
        $this->form['is_active'] = (bool) $attribute->is_active;
        $this->form['sort_order'] = (int) $attribute->sort_order;
        $this->form['payload_text'] = $attribute->payload
            ? json_encode($attribute->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';

        if ($translation) {
            $this->form['locale'] = $translation->locale;
            $this->form['group_name'] = $translation->group_name;
            $this->form['name'] = $translation->name;
            $this->form['slug'] = $translation->slug;
            $this->form['description'] = $translation->description ?? '';
            $this->form['translation_payload_text'] = $translation->payload
                ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '';
        }
    }

    private function loadTranslationForLocale(): void
    {
        if (!$this->attributeId) {
            $this->clearTranslationFields();
            return;
        }

        $translation = AttributeTranslation::query()
            ->where('attribute_id', $this->attributeId)
            ->where('locale', $this->form['locale'])
            ->first();

        if (!$translation) {
            $this->clearTranslationFields();
            return;
        }

        $this->form['group_name'] = $translation->group_name;
        $this->form['name'] = $translation->name;
        $this->form['slug'] = $translation->slug;
        $this->form['description'] = $translation->description ?? '';
        $this->form['translation_payload_text'] = $translation->payload
            ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
    }

    private function clearTranslationFields(): void
    {
        $this->form['group_name'] = '';
        $this->form['name'] = '';
        $this->form['slug'] = '';
        $this->form['description'] = '';
        $this->form['translation_payload_text'] = '';
    }

    /**
     * @return array<mixed>|null|false
     */
    private function decodeJsonField(string $field): array|null|false
    {
        $value = trim((string) data_get($this, $field));
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field, __('Invalid JSON payload.'));
            $this->dispatch('notify', type: 'danger', message: __('Invalid JSON payload.'));
            return false;
        }

        if (!is_array($decoded)) {
            $this->addError($field, __('JSON payload must decode to object/array.'));
            $this->dispatch('notify', type: 'danger', message: __('JSON payload must decode to object/array.'));
            return false;
        }

        return $decoded;
    }
}
