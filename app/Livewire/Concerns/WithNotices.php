<?php

namespace App\Livewire\Concerns;

/**
 * Success and failure messages that survive a Livewire round-trip.
 *
 * `session()->flash()` is rendered by the layout, which Livewire does not re-render on an
 * AJAX request — so a flash set inside an action is invisible until the next full page
 * load. These properties live on the component, so they render with it.
 *
 * Flash is still the right tool when a redirect follows; this is for everything else.
 */
trait WithNotices
{
    public ?string $notice = null;

    /** Either 'status' or 'error'. */
    public string $noticeType = 'status';

    public function notify(string $message): void
    {
        $this->notice = $message;
        $this->noticeType = 'status';
    }

    public function notifyError(string $message): void
    {
        $this->notice = $message;
        $this->noticeType = 'error';
    }

    public function clearNotice(): void
    {
        $this->notice = null;
        $this->noticeType = 'status';
    }
}
