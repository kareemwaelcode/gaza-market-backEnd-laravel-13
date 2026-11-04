<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;

class PointTransactionController extends Controller
{
    /**
     * Read-only ledger: transactions are created by the points service
     * and are never edited or deleted from the CMS.
     */
    public function index()
    {
        $pointTransactions = PointTransaction::with([
            'user:id,user_full_name,user_phone',
            'pointRule:id,point_rule_key,point_rule_name_en',
        ])
            ->orderByDesc('id')
            ->paginate(10);

        return response()->view('cms.point_transaction.index', compact('pointTransactions'));
    }
}