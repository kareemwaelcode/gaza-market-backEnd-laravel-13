<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * هوية الحساب صارت 05[69]xxxxxxx لكل صيغ الرقم (محلي أو 970 أو 972).
 * يحوّل أي رقم مخزّن بصيغة 972 إلى المحلي، ويوقف قبل أي تعديل إذا وُجد تعارض مع رقم محلي موجود
 * (يشمل الحسابات المحذوفة لأن الاستعلام مباشر على الجدول). تغيير بيانات فقط، بدون تغيير مخطط.
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [];

        foreach (DB::table('users')->where('user_phone', 'like', '972%')->get(['id', 'user_phone']) as $row) {
            if (preg_match('/^972(5[69]\d{7})$/', (string) $row->user_phone, $m)) {
                $map[$row->id] = '0'.$m[1];
            }
        }

        if ($map === []) {
            return;
        }

        $conflicts = DB::table('users')->whereIn('user_phone', array_values($map))->pluck('user_phone')->all();

        if ($conflicts !== [] || count($map) !== count(array_unique($map))) {
            throw new RuntimeException('Phone normalization aborted: a local-format number already exists for a 972 user. Resolve it manually first.');
        }

        DB::transaction(function () use ($map) {
            foreach ($map as $id => $phone) {
                DB::table('users')->where('id', $id)->update(['user_phone' => $phone]);
            }
        });
    }

    public function down(): void
    {
        // لا رجوع: لا نعرف أي الأرقام كانت مخزّنة بـ 972 أصلاً.
    }
};