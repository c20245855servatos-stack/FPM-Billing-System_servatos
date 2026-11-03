<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\DomainService;

class ClientController extends Controller
{
    public function __construct(private DomainService $service) {}

    public function index()
    {
        return response()->json(
            Client::withCount('projects')->latest()->paginate(15)
        );
    }

    public function create()
    {
        return response()->json(['fields' => ['company_name', 'email']]);
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->service->createClient($request->validated());

        if (! $request->expectsJson()) {
            return redirect()->back()->with('success', "Client {$client->company_name} created successfully.");
        }

        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $updated = $this->service->updateClient($client, $request->validated());

        return response()->json($updated);
    }
}
