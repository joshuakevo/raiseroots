<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\CreditRegistryService;
use Illuminate\Http\Request;

/**
 * Looks a national ID up in the shared ElTech Credit Registry: loans the person holds at
 * the other ElTech lenders. A search page, plus a results fragment loaded into the client
 * and loan pages after they render (so a slow registry never delays those pages).
 */
class CreditCheckController extends Controller
{
    public function __construct(protected CreditRegistryService $registry) {}

    public function index(Request $request)
    {
        $nationalId   = trim((string) $request->national_id);
        $result       = $nationalId !== '' ? $this->registry->lookup($nationalId) : null;
        $localClients = $nationalId !== ''
            ? Client::where('id_number', $nationalId)->get(['id', 'name', 'client_number'])
            : collect();

        return view('credit-check.index', compact('nationalId', 'result', 'localClients'));
    }

    public function panel(Request $request)
    {
        $result = $this->registry->lookup((string) $request->national_id);

        return view('credit-check._results', compact('result'));
    }
}
