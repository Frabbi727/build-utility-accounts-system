<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\Channels\DatabaseChannel;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\NotificationChannelInterface;
use App\Services\Notification\Channels\PushChannel;
use App\Services\Notification\Channels\SmsChannel;

class ChannelManager
{
    /** @var array<string, NotificationChannelInterface> */
    private array $channels = [];

    public function __construct(
        DatabaseChannel $databaseChannel,
        PushChannel $pushChannel,
        SmsChannel $smsChannel,
        EmailChannel $emailChannel,
    ) {
        $this->registerChannel($databaseChannel);
        $this->registerChannel($pushChannel);
        $this->registerChannel($smsChannel);
        $this->registerChannel($emailChannel);
    }

    public function registerChannel(NotificationChannelInterface $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    /**
     * Dispatch notification across eligible and opted-in channels.
     *
     * @param  list<string>|null  $targetChannels
     * @return array<string, bool>
     */
    public function dispatch(User $user, Notification $notification, ?array $targetChannels = null, string $category = 'bills'): array
    {
        $targetChannels = $targetChannels ?? ['push', 'in_app'];
        $results = [];

        foreach ($targetChannels as $channelName) {
            if (! isset($this->channels[$channelName])) {
                continue;
            }

            // Check resident opt-in preference for this channel & category
            if (! $user->prefersChannel($channelName, $category)) {
                $results[$channelName] = false;

                continue;
            }

            $channel = $this->channels[$channelName];
            $results[$channelName] = $channel->send($user, $notification);
        }

        return $results;
    }
}
