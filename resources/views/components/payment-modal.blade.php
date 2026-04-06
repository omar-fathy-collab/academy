<!-- Unified Payment Modal Component -->
<div x-data="globalPaymentModal()" 
     :class="{ 'd-block show': show }"
     @open-payment-modal.window="open($event.detail)"
     class="modal fade shadow-lg" 
     id="globalPaymentModal" 
     tabindex="-1" 
     aria-hidden="true"
     style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; backdrop-filter: blur(8px); background-color: rgba(0,0,0,0.5);"
     x-cloak
     x-transition>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl theme-card" style="background: var(--card-bg);">
            <!-- Header -->
            <div class="modal-header border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title fw-bold theme-text-main" x-text="title">Record Payment</h5>
                    <p class="text-muted small mb-0" x-text="subtitle"></p>
                </div>
                <button type="button" class="btn-close theme-text-main" @click="show = false" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">
                <form @submit.prevent="submit">
                    <div class="row g-3">
                        <!-- Amount -->
                        <div class="col-12">
                            <label class="form-label small fw-bold theme-text-main">Amount (EGP)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary bg-opacity-10 border-0 text-primary fw-bold">£</span>
                                <input type="number" 
                                       step="0.01" 
                                       class="form-control form-control-lg border-0 bg-light theme-card theme-text-main" 
                                       x-model="form.amount" 
                                       placeholder="0.00" 
                                       required>
                            </div>
                        </div>

                        <!-- Date & Method -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold theme-text-main">Date</label>
                            <input type="date" 
                                   class="form-control border-0 bg-light theme-card theme-text-main" 
                                   x-model="form.date" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold theme-text-main">Method</label>
                            <select class="form-select border-0 bg-light theme-card theme-text-main" x-model="form.payment_method" required>
                                <option value="cash">Cash 💵</option>
                                <option value="bank_transfer">Bank Transfer 🏦</option>
                                <option value="vodafone_cash">Vodafone Cash 📱</option>
                                <option value="fawry">Fawry 💳</option>
                                <option value="instapay">InstaPay 💸</option>
                            </select>
                        </div>

                        <!-- Category (Visible only for Expenses) -->
                        <template x-if="form.type === 'expense'">
                            <div class="col-12">
                                <label class="form-label small fw-bold theme-text-main">Category</label>
                                <select class="form-select border-0 bg-light theme-card theme-text-main" x-model="form.category" required>
                                    <option value="rent">Rent</option>
                                    <option value="utilities">Utilities (Electricity/Water)</option>
                                    <option value="marketing">Marketing & Ads</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="supplies">Supplies & Stationery</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </template>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label small fw-bold theme-text-main">Notes (Optional)</label>
                            <textarea class="form-control border-0 bg-light theme-card theme-text-main" 
                                      x-model="form.notes" 
                                      rows="2" 
                                      placeholder="Add details..."></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid mt-4">
                        <button type="submit" 
                                class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2"
                                :disabled="loading">
                            <template x-if="loading">
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                            </template>
                            <span x-text="loading ? 'Processing...' : 'Confirm Transaction'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function globalPaymentModal() {
    return {
        title: 'Record Payment',
        subtitle: '',
        loading: false,
        show: false,
        form: {
            type: 'student_payment',
            target_id: null,
            amount: '',
            payment_method: 'cash',
            date: new Date().toISOString().split('T')[0],
            notes: '',
            category: 'other'
        },
        open(data) {
            console.log('Payment modal OPEN requested with data:', data);
            this.form.type = data.type || 'student_payment';
            this.form.target_id = data.target_id || null;
            this.form.amount = data.amount || '';
            this.form.payment_method = data.payment_method || 'cash';
            this.form.notes = data.notes || '';
            this.form.category = data.category || 'other';
            this.title = data.title || 'Record Payment';
            this.subtitle = data.subtitle || '';
            
            this.show = true;
            console.log('Modal show state now true');
        },
        async submit() {
            console.log('Submitting payment...', this.form);
            this.loading = true;
            try {
                const response = await fetch('{{ route("financial.record_transaction") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.form)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Close modal
                    this.show = false;
                    
                    // Success feedback
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.message,
                            icon: 'success',
                            confirmButtonColor: '#3085d6',
                            timer: 2000
                        });
                    }
                    
                    // Trigger refresh on the parent component if necessary
                    window.dispatchEvent(new CustomEvent('payment-recorded', { detail: result.data }));
                    
                    // Specific to AJAX Table refresh if present
                    if (window.Alpine && document.querySelector('[x-data*="ajaxTable"]')) {
                        // Find the closest Alpine component that might need a data refresh
                        window.dispatchEvent(new CustomEvent('refresh-table'));
                    }
                } else {
                    alert(result.message || 'Error occurred');
                }
            } catch (error) {
                console.error('Payment error:', error);
                alert('Something went wrong. Please try again.');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
