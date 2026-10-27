<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * A category that an event uses cannot be deleted: the event would lose it. The
 * restrictOnDelete foreign keys refuse it in the database as well.
 */
final class DestroyCategoryRequest extends FormRequest
{
    /** The admin middleware has already checked the role. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $events = $this->category()->taggedEvents()->count();
            if ($events > 0) {
                $validator->errors()->add('category', trans_choice(
                    'This category is used by :count event and cannot be deleted.|This category is used by :count events and cannot be deleted.',
                    $events,
                ));
            }
        }];
    }

    /** The category from the route, whose events after() counts. */
    public function category(): Category
    {
        $category = $this->route('category');
        throw_unless($category instanceof Category, LogicException::class, 'The category route parameter must be bound.');

        return $category;
    }
}
