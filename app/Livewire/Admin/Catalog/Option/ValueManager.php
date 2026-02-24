<?php

namespace App\Livewire\Admin\Catalog\Option;

use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Option\OptionValueTranslation;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ValueManager extends Component
{
    use WithPagination;

    public int $optionId;
    public string $search = '';
    public string $locale = 'en';
    public ?int $editingId = null;

    public array $form = [
        'code' => '',
        'is_active' => true,
        'sort_order' => 0,
        'payload_text' => '',
        'name' => '',
        'slug' => '',
        'translation_payload_text' => '',
    ];

    public function mount(int $optionId): void
    {
        $this->optionId = $optionId;
        $this->locale = (string) (request()->query('locale') ?: config('app.locale', 'en'));

        Option::query()->findOrFail($this->optionId);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLocale(): void
    {
        $this->resetPage();

        if ($this->editingId) {
            $this->loadTranslationForLocale();
        }
    }

    public function generateSlug(): void
    {
        $name = trim((string) $this->form['name']);
        if ($name !== '') {
            $this->form['slug'] = Str::slug($name);
        }
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $payload = $this->decodeJsonField('form.payload_text');
        if ($payload === false) {
            return;
        }

        $translationPayload = $this->decodeJsonField('form.translation_payload_text');
        if ($translationPayload === false) {
            return;
        }

        $userId = auth()->id();
        $wasEditing = (bool) $this->editingId;

        DB::transaction(function () use ($validated, $payload, $translationPayload, $userId, $wasEditing): void {
            $valueData = [
                'option_id' => $this->optionId,
                'code' => trim((string) $validated['form']['code']),
                'is_active' => (bool) $validated['form']['is_active'],
                'sort_order' => (int) $validated['form']['sort_order'],
                'payload' => $payload,
                'updated_by' => $userId,
            ];

            if ($this->editingId) {
                $value = OptionValue::query()
                    ->where('option_id', $this->optionId)
                    ->findOrFail($this->editingId);

                $value->fill($valueData)->save();
            } else {
                $value = OptionValue::query()->create($valueData + ['created_by' => $userId]);
                $this->editingId = $value->id;
            }

            $value->translations()->updateOrCreate(
                ['locale' => $this->locale],
                [
                    'name' => $validated['form']['name'],
                    'slug' => $validated['form']['slug'],
                    'payload' => $translationPayload,
                ]
            );

            activity('catalog_options')
                ->performedOn($value)
                ->causedBy(auth()->user())
                ->event($wasEditing ? 'value_updated' : 'value_created')
                ->withProperties([
                    'option_id' => $this->optionId,
                    'locale' => $this->locale,
                    'slug' => $validated['form']['slug'],
                ])
                ->log('Option value saved');
        });

        $this->dispatch('notify', type: 'success', message: $wasEditing ? __('Value updated.') : __('Value created.'));
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $value = OptionValue::query()
            ->where('option_id', $this->optionId)
            ->with('translations')
            ->findOrFail($id);

        $translation = $value->translations->firstWhere('locale', $this->locale)
            ?? $value->translations->firstWhere('locale', config('app.locale', 'en'))
            ?? $value->translations->first();

        $this->editingId = $value->id;
        $this->form['code'] = $value->code;
        $this->form['is_active'] = (bool) $value->is_active;
        $this->form['sort_order'] = (int) $value->sort_order;
        $this->form['payload_text'] = $value->payload
            ? json_encode($value->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';

        if ($translation) {
            $this->form['name'] = $translation->name;
            $this->form['slug'] = $translation->slug;
            $this->form['translation_payload_text'] = $translation->payload
                ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '';
        } else {
            $this->clearTranslationFields();
        }
    }

    public function delete(int $id): void
    {
        $value = OptionValue::query()
            ->where('option_id', $this->optionId)
            ->findOrFail($id);

        activity('catalog_options')
            ->performedOn($value)
            ->causedBy(auth()->user())
            ->event('value_deleted')
            ->withProperties(['option_id' => $this->optionId])
            ->log('Option value deleted');

        $value->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('notify', type: 'success', message: __('Value deleted.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $perPage = app(SystemSettingsService::class)->getInt(
            'admin_items_per_page',
            (int) config('admin_ui.pagination.admin_items_per_page', 20),
            5,
            200
        );

        $option = Option::query()
            ->with([
                'translations' => fn ($q) => $q->where('locale', $this->locale),
            ])
            ->findOrFail($this->optionId);

        $rows = OptionValue::query()
            ->where('option_id', $this->optionId)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $this->locale),
            ])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('code', 'like', '%'.$this->search.'%')
                        ->orWhereHas('translations', function ($tq): void {
                            $tq->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('slug', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return view('livewire.admin.catalog.option.values', [
            'option' => $option,
            'rows' => $rows,
            'perPage' => $perPage,
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
                Rule::unique('catalog_option_values', 'code')
                    ->where(fn ($q) => $q->where('option_id', $this->optionId))
                    ->ignore($this->editingId),
            ],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['nullable', 'integer', 'min:0'],
            'form.payload_text' => ['nullable', 'string'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.slug' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('catalog_option_value_translations', 'slug')
                    ->where(fn ($q) => $q->where('locale', $this->locale))
                    ->ignore($this->editingId, 'option_value_id'),
            ],
            'form.translation_payload_text' => ['nullable', 'string'],
        ];
    }

    private function loadTranslationForLocale(): void
    {
        if (!$this->editingId) {
            $this->clearTranslationFields();
            return;
        }

        $translation = OptionValueTranslation::query()
            ->where('option_value_id', $this->editingId)
            ->where('locale', $this->locale)
            ->first();

        if (!$translation) {
            $this->clearTranslationFields();
            return;
        }

        $this->form['name'] = $translation->name;
        $this->form['slug'] = $translation->slug;
        $this->form['translation_payload_text'] = $translation->payload
            ? json_encode($translation->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : '';
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'is_active' => true,
            'sort_order' => 0,
            'payload_text' => '',
            'name' => '',
            'slug' => '',
            'translation_payload_text' => '',
        ];
    }

    private function clearTranslationFields(): void
    {
        $this->form['name'] = '';
        $this->form['slug'] = '';
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
