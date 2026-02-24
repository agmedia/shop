<?php

namespace App\Livewire\Admin\Content\Comment;

use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\Page\InfoPage;
use App\Models\Content\Support\Comment;
use App\Models\Content\Support\Faq;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Livewire\Component;
use Livewire\WithPagination;

class Manager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $locale = 'all';
    public string $status = Comment::STATUS_PENDING;
    public string $target = 'all';

    public function mount(): void
    {
        $this->locale = (string) (request()->query('locale') ?: 'all');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLocale(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTarget(): void
    {
        $this->resetPage();
    }

    public function approve(int $commentId): void
    {
        $this->setStatus($commentId, Comment::STATUS_APPROVED);
    }

    public function reject(int $commentId): void
    {
        $this->setStatus($commentId, Comment::STATUS_REJECTED);
    }

    public function spam(int $commentId): void
    {
        $this->setStatus($commentId, Comment::STATUS_SPAM);
    }

    public function delete(int $commentId): void
    {
        $comment = Comment::query()->find($commentId);
        if (!$comment) {
            $this->dispatch('notify', type: 'warning', message: __('Comment not found.'));
            return;
        }

        $comment->delete();

        activity('content_comments')
            ->performedOn($comment)
            ->causedBy(auth()->user())
            ->event('deleted')
            ->log(__('Comment moved to trash'));

        $this->dispatch('notify', type: 'info', message: __('Comment moved to trash.'));
    }

    public function restore(int $commentId): void
    {
        $comment = Comment::query()->withTrashed()->find($commentId);
        if (!$comment || !$comment->trashed()) {
            $this->dispatch('notify', type: 'warning', message: __('Comment not in trash.'));
            return;
        }

        $comment->restore();

        activity('content_comments')
            ->performedOn($comment)
            ->causedBy(auth()->user())
            ->event('restored')
            ->log(__('Comment restored'));

        $this->dispatch('notify', type: 'success', message: __('Comment restored.'));
    }

    public function render()
    {
        $perPage = app(SystemSettingsService::class)->getInt(
            'admin_items_per_page',
            (int) config('admin_ui.pagination.admin_items_per_page', 20),
            5,
            200
        );

        $query = Comment::query()
            ->with([
                'user:id,name,email',
                'reviewer:id,name',
                'commentable' => function (MorphTo $morphTo): void {
                    $locale = $this->locale !== 'all' ? $this->locale : (string) config('app.locale', 'en');
                    $morphTo->morphWith([
                        Product::class => ['translations' => fn ($q) => $q->where('locale', $locale)],
                        BlogPost::class => ['translations' => fn ($q) => $q->where('locale', $locale)],
                        InfoPage::class => ['translations' => fn ($q) => $q->where('locale', $locale)],
                        Faq::class => ['translations' => fn ($q) => $q->where('locale', $locale)],
                    ]);
                },
            ]);

        if ($this->status === 'deleted') {
            $query->onlyTrashed();
        } else {
            if ($this->status === 'all') {
                $query->withTrashed();
            } else {
                $query->where('status', $this->status);
            }
        }

        if ($this->target !== 'all') {
            $targetClass = $this->targetClass($this->target);
            if ($targetClass) {
                $query->where('commentable_type', $targetClass);
            }
        }

        if ($this->locale !== 'all') {
            $query->where('locale', $this->locale);
        }

        if ($this->search !== '') {
            $query->where(function (Builder $q): void {
                $q->where('body', 'like', '%'.$this->search.'%')
                    ->orWhere('author_name', 'like', '%'.$this->search.'%')
                    ->orWhere('author_email', 'like', '%'.$this->search.'%');
            });
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return view('livewire.admin.content.comment.manager', [
            'rows' => $rows,
            'perPage' => $perPage,
            'statusOptions' => $this->statusOptions(),
            'targetOptions' => $this->targetOptions(),
        ]);
    }

    private function setStatus(int $commentId, string $status): void
    {
        if (!in_array($status, Comment::statuses(), true)) {
            return;
        }

        $comment = Comment::query()->find($commentId);
        if (!$comment) {
            $this->dispatch('notify', type: 'warning', message: __('Comment not found.'));
            return;
        }

        $comment->update([
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        activity('content_comments')
            ->performedOn($comment)
            ->causedBy(auth()->user())
            ->event('moderated')
            ->withProperties(['status' => $status])
            ->log(__('Comment status changed'));

        $this->dispatch('notify', type: 'success', message: __('Comment status updated.'));
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            Comment::STATUS_PENDING => __('Pending'),
            Comment::STATUS_APPROVED => __('Approved'),
            Comment::STATUS_REJECTED => __('Rejected'),
            Comment::STATUS_SPAM => __('Spam'),
            'all' => __('All'),
            'deleted' => __('Trash'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function targetOptions(): array
    {
        return [
            'all' => __('All Targets'),
            'product' => __('Products'),
            'blog' => __('Blog Posts'),
            'page' => __('Info Pages'),
            'faq' => __('FAQs'),
        ];
    }

    private function targetClass(string $key): ?string
    {
        return match ($key) {
            'product' => Product::class,
            'blog' => BlogPost::class,
            'page' => InfoPage::class,
            'faq' => Faq::class,
            default => null,
        };
    }
}
