@extends('cms.parent')

@section('title', 'index point transaction')

@section('styles')
@endsection


@section('content')
    <!-- Point transactions table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="point-transactions-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="point-transactions-title">Point Transactions</h2>
            <p class="m-card__subtitle">History of every time a user earned or lost points</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Rule</th>
                        <th>Points</th>
                        <th>Source</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $pointTransactions as $pointTransaction )
                    <tr>
                        <td>{{ $pointTransactions->firstItem() + $loop->index }}</td>
                        <td>{{ $pointTransaction->user?->user_full_name }}</td>
                        <td>{{ $pointTransaction->user?->user_phone }}</td>
                        <td>{{ $pointTransaction->pointRule?->point_rule_name_en }}</td>
                        <td>{{ $pointTransaction->point_transaction_points > 0 ? '+' : '' }}{{ $pointTransaction->point_transaction_points }}</td>
                        <td>{{ $pointTransaction->point_transaction_source_type ? $pointTransaction->point_transaction_source_type.' #'.$pointTransaction->point_transaction_source_id : '-' }}</td>
                        <td>{{ $pointTransaction->point_transaction_created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    {{ $pointTransactions->links() }}
</div>
</div>

@endsection