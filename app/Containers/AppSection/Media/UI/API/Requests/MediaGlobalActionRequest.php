<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class MediaGlobalActionRequest extends ParentRequest
{
    protected array $decode = ['selected.*.id', 'destination', 'item.id', 'imageId'];

    protected function prepareForValidation(): void
    {
        // destination=0 means root folder, not a hashed ID.
        // Must use $this->get() (Symfony's ParameterBag) to bypass Apiato's input() override,
        // because hashids()->decode("0") returns null, making $this->input('destination') = null.
        $rawDestination = $this->get('destination');
        if ($rawDestination !== null && (string) $rawDestination === '0') {
            $this->merge(['destination' => 0]);
            $this->decode = array_values(array_diff($this->decode, ['destination']));
        }
    }

    public function rules(): array
    {
        $rules = [
            'action' => ['required', 'string', Rule::in([
                'trash',
                'restore',
                'move',
                'make_copy',
                'delete',
                'favorite',
                'remove_favorite',
                'add_recent',
                'crop',
                'rename',
                'alt_text',
                'empty_trash',
                'properties',
            ])],
            'selected' => ['sometimes', 'array'],
            'selected.*.id' => ['sometimes', 'integer', 'min:1'],
            'selected.*.is_folder' => ['sometimes', 'boolean'],
        ];

        $action = $this->input('action');
        if ($action === 'move') {
            $rules['destination'] = ['required', 'integer', 'min:0'];
        }

        if ($action === 'rename') {
            $rules['selected'] = ['required', 'array'];
            $rules['selected.*.name'] = ['required', 'string', 'max:120'];
            $rules['selected.*.rename_physical_file'] = ['sometimes', 'boolean'];
        }

        if ($action === 'alt_text') {
            $rules['selected'] = ['required', 'array'];
            $rules['selected.*.alt'] = ['nullable', 'string', 'max:220'];
        }

        if ($action === 'properties') {
            $rules['color'] = ['required', 'string', 'max:20'];
            $rules['selected'] = ['required', 'array'];
        }

        if ($action === 'add_recent') {
            $rules['item.id'] = ['required', 'integer', 'min:1'];
            $rules['item.is_folder'] = ['sometimes', 'boolean'];
        }

        if ($action === 'crop') {
            $rules['imageId'] = ['required', 'integer', 'min:1'];
            $rules['cropData'] = ['required'];
        }

        if (in_array($action, ['trash', 'restore', 'make_copy', 'delete', 'favorite', 'remove_favorite'], true)) {
            $rules['selected'] = ['required', 'array'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $action = $this->input('action');

        return match ($action) {
            'trash' => $user->can('files.trash') || $user->can('folders.trash'),
            'restore', 'move', 'rename', 'properties' => $user->can('files.edit') || $user->can('folders.edit'),
            'make_copy' => $user->can('files.create') || $user->can('folders.create'),
            'delete', 'empty_trash' => $user->can('files.destroy') || $user->can('folders.destroy'),
            'alt_text', 'crop' => $user->can('files.edit'),
            'favorite', 'remove_favorite', 'add_recent' => $user->can('media.index'),
            default => $user->can('media.index'),
        };
    }
}
