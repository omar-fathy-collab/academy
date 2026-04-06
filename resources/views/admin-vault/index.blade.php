@extends('layouts.authenticated')

@section('title', 'Admin Finance Vault')

@section('content')
<div class="row g-4 theme-text-main" x-data="adminVault({
    vaults: {{ json_encode($vaults) }},
    totalBalance: {{ $totalBalance }},
    totalCapital: {{ $totalCapital }},
    netProfit: {{ $netProfit }},
    recentWithdrawals: {{ json_encode($recentWithdrawals) }},
    userVault: {{ json_encode($userVaultDetails) }}
})" x-cloak>
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold theme-text-main mb-1">🏛️ Admin Finance Vault</h2>
                <p class="text-muted small mb-0">Overview of capital, profits, and partner balances</p>
            </div>
            <div class="d-flex gap-2">
                <button @click="showAddCapital = true" class="btn btn-outline-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                    <i class="fas fa-plus-circle me-2"></i> Add Capital
                </button>
                <button @click="showNewVault = true" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                    <i class="fas fa-wallet me-2"></i> Create Partner Vault
                </button>
            </div>
        </div>
    </div>

    <!-- Main Stats Row -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-primary h-100">
            <div class="card-body p-4">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Net Profit</p>
                <h3 class="fw-bold text-primary mb-0">£<span x-text="netProfit.toLocaleString()"></span></h3>
                <div class="mt-2 smaller text-muted">Academy life-to-date profit</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-info h-100">
            <div class="card-body p-4">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Capital</p>
                <h3 class="fw-bold text-info mb-0">£<span x-text="totalCapital.toLocaleString()"></span></h3>
                <div class="mt-2 smaller text-muted">Invested partner capital</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-success h-100">
            <div class="card-body p-4">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Vault Balances</p>
                <h3 class="fw-bold text-success mb-0">£<span x-text="totalBalance.toLocaleString()"></span></h3>
                <div class="mt-2 smaller text-muted">Capital + Earned - Withdrawn</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-warning h-100">
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                <p class="text-muted smaller fw-bold text-uppercase mb-1 text-start">My Available Profit</p>
                <div class="d-flex align-items-center justify-content-between">
                    <h3 class="fw-bold text-warning mb-0" x-text="'£' + (userVault?.available_balance || 0).toLocaleString()"></h3>
                    <button class="btn btn-sm btn-warning rounded-pill fw-bold smaller px-3" @click="showWithdrawModal = true">Withdraw</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Vaults List -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
            <div class="card-header theme-badge-bg border-bottom-0 p-4">
                <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-users-cog text-primary me-2"></i> Partner Vaults & Shares</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="theme-badge-bg text-muted small text-uppercase">
                            <tr>
                                <th class="px-4 py-3">Partner</th>
                                <th class="py-3 text-center">Share %</th>
                                <th class="py-3">Capital</th>
                                <th class="py-3">Total Earned</th>
                                <th class="py-3">Balance</th>
                                <th class="px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="v in vaults" :key="v.id">
                                <tr class="theme-border">
                                    <td class="px-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                                <i class="fas fa-user-tie"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold theme-text-main" x-text="v.user.username"></div>
                                                <div class="smaller text-muted" x-text="v.user.email"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill px-3 py-1" x-text="v.profit_percentage + '%'"></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold theme-text-main">£<span x-text="Number(v.actual_capital).toLocaleString()"></span></div>
                                    </td>
                                    <td>
                                        <div class="smaller text-muted">Profit: £<span x-text="Number(v.profit_share).toLocaleString()"></span></div>
                                        <div class="smaller text-muted mt-1">Withdrawn: £<span x-text="Number(v.total_withdrawn).toLocaleString()"></span></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success">£<span x-text="Number(v.balance).toLocaleString()"></span></div>
                                        <div class="smaller text-warning mt-1" x-text="'Avail: £' + Number(v.available_balance).toLocaleString()"></div>
                                    </td>
                                    <td class="px-4 text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border theme-border rounded-circle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v text-muted"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 theme-card">
                                                <li><a class="dropdown-item smaller" href="#" @click.prevent="openEditVault(v)"><i class="fas fa-edit me-2 text-warning"></i> Edit Split</a></li>
                                                <li><a class="dropdown-item smaller" href="#" @click.prevent="openHistory(v)"><i class="fas fa-history me-2 text-info"></i> History</a></li>
                                                <li><hr class="dropdown-divider theme-border"></li>
                                                <li><a class="dropdown-item smaller text-danger" href="#" @click.prevent="deleteVault(v.id)"><i class="fas fa-trash-alt me-2"></i> Close Vault</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity / Withdrawals -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
            <div class="card-header theme-badge-bg border-bottom-0 p-4">
                <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-receipt text-success me-2"></i> Recent Withdrawals</h5>
            </div>
            <div class="card-body p-4">
                <div class="timeline">
                    <template x-for="w in recentWithdrawals" :key="w.id">
                        <div class="pb-3 mb-3 border-bottom theme-border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0" x-text="w.user.username"></h6>
                                <span class="badge rounded-pill smaller" :class="getStatusClass(w.status)" x-text="w.status"></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold text-primary">£<span x-text="Number(w.amount).toLocaleString()"></span></span>
                                <span class="smaller text-muted" x-text="new Date(w.created_at).toLocaleDateString()"></span>
                            </div>
                            <template x-if="w.status === 'pending'">
                                <div class="mt-2 d-flex gap-2">
                                    <button class="btn btn-sm btn-success smaller px-3 rounded-pill fw-bold" @click="approveWithdrawal(w.id)">Approve</button>
                                    <button class="btn btn-sm btn-outline-danger smaller px-3 rounded-pill fw-bold" @click="rejectWithdrawal(w.id)">Reject</button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="recentWithdrawals.length === 0">
                        <div class="text-center py-4 text-muted">
                            <p class="smaller mb-0">No recent transactions</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Capital Modal -->
    <div class="modal fade" :class="showAddCapital ? 'show d-block' : ''" tabindex="-1" x-show="showAddCapital" @click.away="showAddCapital = false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content theme-card border-0 shadow-lg rounded-4">
                <form @submit.prevent="submitAddCapital">
                    <div class="modal-header border-0 theme-badge-bg p-4">
                        <h5 class="modal-title fw-bold theme-text-main"><i class="fas fa-plus-circle text-primary me-2"></i> Add Capital to Vault</h5>
                        <button type="button" class="btn-close shadow-none" @click="showAddCapital = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-4">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Target Vault / Partner</label>
                            <select x-model="capitalForm.vault_id" class="form-select theme-badge-bg theme-text-main theme-border rounded-3 py-2 shadow-none" required>
                                <option value="">Select a partner vault...</option>
                                <template x-for="v in vaults" :key="v.id">
                                    <option :value="v.id" x-text="v.user.username + ' (' + v.profit_percentage + '%)'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Amount (EGP)</label>
                            <div class="input-group border theme-border rounded-3 overflow-hidden">
                                <span class="input-group-text theme-badge-bg border-0 theme-text-main">£</span>
                                <input type="number" x-model="capitalForm.amount" class="form-control theme-badge-bg theme-text-main border-0 py-2 shadow-none" placeholder="0.00" step="0.01" required />
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Internal Note</label>
                            <textarea x-model="capitalForm.description" class="form-control theme-badge-bg theme-text-main theme-border rounded-3 py-2 shadow-none" rows="2" placeholder="Reference for this capital addition..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm transition-hover">
                            Confirm Capital Addition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Withdraw Profit Modal -->
    <div class="modal fade" :class="showWithdrawModal ? 'show d-block' : ''" tabindex="-1" x-show="showWithdrawModal" @click.away="showWithdrawModal = false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content theme-card border-0 shadow-lg rounded-4 overflow-hidden">
                <form @submit.prevent="submitWithdrawal">
                    <div class="modal-header border-0 theme-badge-bg p-4">
                        <div class="text-start">
                            <h5 class="modal-title fw-bold theme-text-main mb-1">Request Profit Withdrawal</h5>
                            <p class="text-muted small mb-0">Withdrawal from your available balance</p>
                        </div>
                        <button type="button" class="btn-close shadow-none" @click="showWithdrawModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert bg-warning bg-opacity-10 border-0 rounded-4 p-3 mb-4">
                            <div class="d-flex">
                                <div class="flex-shrink-0 text-warning"><i class="fas fa-info-circle fa-lg"></i></div>
                                <div class="ms-3">
                                    <h6 class="fw-bold text-warning mb-1">Available for Withdrawal</h6>
                                    <p class="small mb-0 text-dark opacity-75">You can withdraw up to £<span class="fw-bold" x-text="(userVault?.available_balance || 0).toLocaleString()"></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Withdrawal Amount</label>
                            <div class="input-group border theme-border rounded-3 overflow-hidden shadow-sm">
                                <span class="input-group-text theme-badge-bg border-0 theme-text-main">£</span>
                                <input type="number" x-model="withdrawForm.amount" class="form-control theme-badge-bg theme-text-main border-0 py-3 shadow-none fw-bold" placeholder="0.00" step="0.01" :max="userVault?.available_balance" required />
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Withdrawal Method / Notes</label>
                            <textarea x-model="withdrawForm.notes" class="form-control theme-badge-bg theme-text-main theme-border rounded-3 py-2 shadow-none" rows="3" placeholder="e.g. Bank Transfer, Cash, or reason for withdrawal..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-warning w-100 py-3 rounded-pill fw-bold shadow-sm transition-hover" :disabled="!withdrawForm.amount || withdrawForm.amount <= 0 || withdrawForm.amount > userVault?.available_balance">
                            Submit Withdrawal Request
                        </button>
                        <p class="w-100 text-center text-muted smaller mt-3 mb-0">Request will be reviewed by academy audit before approval.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Partner Vault Modal -->
    <div class="modal fade" :class="showNewVault ? 'show d-block' : ''" tabindex="-1" x-show="showNewVault" @click.away="showNewVault = false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content theme-card border-0 shadow-lg rounded-4">
                <form @submit.prevent="submitNewVault">
                    <div class="modal-header border-0 theme-badge-bg p-4">
                        <h5 class="modal-title fw-bold theme-text-main"><i class="fas fa-wallet text-success me-2"></i> Create New Partner Vault</h5>
                        <button type="button" class="btn-close shadow-none" @click="showNewVault = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-4">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Select Admin/Partner</label>
                            <select x-model="newVaultForm.user_id" class="form-select theme-badge-bg theme-text-main theme-border rounded-3 py-2 shadow-none" required>
                                <option value="">Choose an eligible administrator...</option>
                                @foreach($eligibleAdmins as $admin)
                                    <option value="{{ $admin->id }}">{{ $admin->username }} ({{ $admin->adminType->name ?? 'Admin' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Profit Share Percentage</label>
                            <div class="input-group border theme-border rounded-3 overflow-hidden">
                                <input type="number" x-model="newVaultForm.profit_percentage" class="form-control theme-badge-bg theme-text-main border-0 py-2 shadow-none" placeholder="e.g. 10" min="0" max="100" required />
                                <span class="input-group-text theme-badge-bg border-0 theme-text-main">%</span>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label smaller fw-bold text-muted text-uppercase mb-2">Initial Capital Investment (Optional)</label>
                            <div class="input-group border theme-border rounded-3 overflow-hidden">
                                <span class="input-group-text theme-badge-bg border-0 theme-text-main">£</span>
                                <input type="number" x-model="newVaultForm.initial_balance" class="form-control theme-badge-bg theme-text-main border-0 py-2 shadow-none" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm transition-hover">
                            Create & Activate Vault
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-backdrop fade show" x-show="showAddCapital || showWithdrawModal || showNewVault"></div>
</div>

<script>
function adminVault(config) {
    return {
        vaults: config.vaults,
        totalBalance: config.totalBalance,
        totalCapital: config.totalCapital,
        netProfit: config.netProfit,
        recentWithdrawals: config.recentWithdrawals,
        userVault: config.userVault,
        
        showAddCapital: false,
        showNewVault: false,
        showWithdrawModal: false,
        activeAdminId: {{ auth()->id() }},

        capitalForm: { vault_id: '', amount: '', description: '', user_id: {{ auth()->id() }} },
        withdrawForm: { amount: '', notes: '' },
        newVaultForm: { user_id: '', profit_percentage: '', initial_balance: '' },

        submitAddCapital() {
            axios.post('{{ route("admin.vault.add_capital") }}', this.capitalForm)
                .then(res => {
                    if (res.data.success) {
                        Swal.fire('Success', res.data.message, 'success').then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', res.data.message || 'Validation failed', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'System failure adding capital', 'error'));
        },

        submitWithdrawal() {
            axios.post('{{ route("admin.vault.withdraw") }}', this.withdrawForm)
                .then(res => {
                    if (res.data.success) {
                        Swal.fire('Request Sent', 'Your withdrawal request has been submitted for review.', 'success').then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', res.data.message || 'Insufficient balance', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'Failed to submit withdrawal request', 'error'));
        },

        submitNewVault() {
            axios.post('{{ route("admin.vault.store") }}', this.newVaultForm)
                .then(res => {
                    if (res.data.success) {
                        Swal.fire('Activated', 'New partner vault created successfully.', 'success').then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', res.data.message || 'Error creating vault', 'error');
                    }
                })
                .catch(err => Swal.fire('Error', 'System failure creating vault', 'error'));
        },

        getStatusClass(status) {
            switch(status?.toLowerCase()) {
                case 'approved': return 'bg-success bg-opacity-10 text-success border border-success';
                case 'pending': return 'bg-warning bg-opacity-10 text-warning border border-warning';
                case 'rejected': return 'bg-danger bg-opacity-10 text-danger border border-danger';
                default: return 'bg-secondary bg-opacity-10 text-muted border';
            }
        },

        approveWithdrawal(id) {
            Swal.fire({
                title: 'Confirm Approval',
                text: "This will deduct the amount from the partner's vault balance.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(window.route('admin.vault.approve_withdrawal', id))
                        .then(() => window.location.reload());
                }
            });
        }
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .transition-hover:hover { transform: translateY(-2px); }
</style>
@endsection
