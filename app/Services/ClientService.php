<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientService
{
    public function createClient(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            return Client::create([
                'company_name' => trim(strip_tags($data['company_name'])),
                'email' => $this->sanitizeEmail($data['email']),
            ]);
        });
    }

    public function updateClient(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($data, $client) {
            if (isset($data['company_name'])) {
                $client->company_name = trim(strip_tags($data['company_name']));
            }
            if (isset($data['email'])) {
                $client->email = $this->sanitizeEmail($data['email']);
            }

            $client->save();

            return $client->fresh();
        });
    }

    public function sanitizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
