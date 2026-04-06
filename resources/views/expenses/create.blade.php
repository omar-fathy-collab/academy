@extends('layouts.authenticated')

@section('title', 'Add New Expense')

@section('content')
<div class="row justify-content-center pt-4">
    <div class="col-lg-7">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('expenses.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                <i class="fas fa-arrow-left fa-lg"></i>
            </a>
            <div>
                <h2 class="fw-bold theme-text-main mb-1">Add Expense</h2>
                <p class="text-muted small mb-0">Record a new academy expenditure</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
            <div class="card-header theme-badge-bg border-bottom-0 p-4">
                <h5 class="fw-bold theme-text-main mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> Expense Details</h5>
            </div>
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('expenses.store') }}" method="POST">
                    @csrf
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select rounded-3 theme-badge-bg theme-text-main theme-border" required>
                                <option value="">Select Category...</option>
                                @foreach($categories as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('category') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Payment Method</label>
                            <select name="payment_method" class="form-select rounded-3 theme-badge-bg theme-text-main theme-border">
                                <option value="">Select Method...</option>
                                @foreach($payment_methods as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Amount (EGP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light theme-border">£</span>
                                <input type="number" name="amount" step="0.01" class="form-control theme-badge-bg theme-text-main theme-border" placeholder="0.00" required />
                            </div>
                            @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" value="{{ date('Y-m-d') }}" required />
                            @error('expense_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold small text-muted">Description / Notes <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" rows="4" placeholder="Briefly describe the expense..." required></textarea>
                            @error('description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-primary py-3 rounded-pill shadow w-100 fw-bold text-uppercase transition-hover">
                                <i class="fas fa-save me-2"></i> Save Expense
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .transition-hover { transition: all 0.3s ease; }
    .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important; }
</style>
@endsection
