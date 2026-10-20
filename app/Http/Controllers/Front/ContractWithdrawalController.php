<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Jobs\SendWithdrawalReceipt;
use App\Models\Content\Support\ContactMessage;
use App\Models\Sales\ContractWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractWithdrawalController extends Controller
{
    use ResolvesFrontendView;

    public function create(Request $request)
    {
        return view('front.returns.withdrawal', ['layout' => $this->frontendView($request, 'layouts.store')]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'order_number' => ['required', 'string', 'max:80'],
            'items' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['items'] = $data['items'] ?? '';
        $data = array_map(static fn ($value) => trim((string) $value), $data);
        $token = (string) Str::uuid();
        $request->session()->put('withdrawal.'.$token, $data + ['locale' => app()->getLocale()]);

        return view('front.returns.withdrawal', [
            'layout' => $this->frontendView($request, 'layouts.store'),
            'draft' => $data,
            'token' => $token,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['token' => ['required', 'uuid']]);
        $token = (string) $request->input('token');
        $draft = $request->session()->get('withdrawal.'.$token);
        abort_unless(is_array($draft), 419);

        app()->setLocale($draft['locale']);

        $withdrawal = DB::transaction(function () use ($draft, $token, $request) {
            $withdrawal = ContractWithdrawal::query()->firstOrCreate(['id' => $token], [
                'customer_name' => $draft['customer_name'],
                'email' => $draft['email'],
                'order_number' => $draft['order_number'],
                'items' => $draft['items'] ?? '',
                'statement' => __('compliance.statement', ['order' => $draft['order_number']]),
                'locale' => app()->getLocale(),
                'received_at' => now(),
            ]);
            if ($withdrawal->wasRecentlyCreated) {
                ContactMessage::query()->create([
                    'name' => $withdrawal->customer_name,
                    'email' => $withdrawal->email,
                    'subject' => __('compliance.receipt_subject', ['order' => $withdrawal->order_number]),
                    'message' => $withdrawal->statement."\n".($withdrawal->items ?: __('compliance.entire_order'))."\n".$withdrawal->received_at->toIso8601String(),
                    'status' => ContactMessage::STATUS_NEW,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payload' => ['type' => 'contract_withdrawal', 'withdrawal_id' => $token],
                ]);
                // Persist the mail task with the declaration. It survives mail outages and is retried by a worker.
                SendWithdrawalReceipt::dispatch($token)->onConnection('database');
            }

            return $withdrawal;
        });

        return view('front.returns.withdrawal', [
            'layout' => $this->frontendView($request, 'layouts.store'),
            'withdrawal' => $withdrawal,
        ]);
    }
}
