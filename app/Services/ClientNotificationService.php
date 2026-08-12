<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SystemSetting;

class ClientNotificationService
{
    public function __construct(protected SmsService $sms) {}

    public function clientWelcomed(Client $client): void
    {
        if (!$client->phone) return;

        $org = SystemSetting::get('org_name', 'ElTech Finance');

        $message = sprintf(
            "Dear %s, welcome to %s! Your client number is %s. Thank you for joining us - visit any branch or contact us if you need assistance. - %s",
            $client->name,
            $org,
            $client->client_number,
            $org
        );

        $this->sms->send($client->phone, $message, [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'recipient_group' => 'client_welcome',
        ]);
    }
}
