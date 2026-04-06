export default () => ({
    loading: false,
    
    init() {
        let rootElement = this.$el ? (this.$el.closest('[x-data]') || document) : document;
        console.log('AJAX Table Ready:', rootElement);
    },
    
    async updateList(e = null, targetUrl = null) {
        let url = targetUrl;
        let rootElement = this.$el ? (this.$el.closest('[x-data]') || document) : document;
        
        if (!url) {
            const form = rootElement.querySelector('form.ajax-form') || rootElement.querySelector('form');
            if (form) {
                const formData = new FormData(form);
                const params = new URLSearchParams();
                for (const [key, value] of formData.entries()) {
                    params.append(key, value);
                }
                const actionUrl = form.getAttribute('action') || window.location.pathname;
                url = `${actionUrl.split('?')[0]}?${params.toString()}`;
            } else {
                url = window.location.href;
            }
        }

        this.loading = true;
        try {
            console.log('AJAX Table fetching URL:', url);
            let htmlText = '';
            
            // Prefer axios if available (handles CSRF, cookies, and headers cleanly)
            if (typeof window.axios !== 'undefined') {
                const response = await window.axios.get(url, {
                    headers: { 'Accept': 'text/html' }
                });
                htmlText = response.data;
            } else {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                });
                if (!response.ok) throw new Error('Network response was not ok');
                htmlText = await response.text();
            }
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            
            // Search for components to update - try locally first, then globally
            let localContents = Array.from(rootElement.querySelectorAll('.ajax-content'));
            
            // If the element itself has the class, include it
            if (rootElement.classList && rootElement.classList.contains('ajax-content')) {
                localContents.unshift(rootElement);
            }

            // Fallback: If no local ajax-contents found, try searching the whole document
            // (useful if IDs moved or the x-data scope changed)
            if (localContents.length === 0) {
                console.log('AJAX Table: trying global .ajax-content search');
                localContents = Array.from(document.querySelectorAll('.ajax-content'));
            }

            console.log('AJAX Table: updating contents for:', localContents.map(el => el.id || 'no-id'));
            let updated = false;

            localContents.forEach(el => {
                if (el.id) {
                    let fetchedEl = doc.getElementById(el.id);
                    
                    // Fallback to querySelector if getElementById fails
                    if (!fetchedEl) {
                        fetchedEl = doc.querySelector('#' + el.id.replace(/(:|\.|\[|\]|,|=|@)/g, "\\$1"));
                    }

                    if (fetchedEl) {
                        el.innerHTML = fetchedEl.innerHTML;
                        console.log('AJAX Table: Element updated:', el.id);
                        updated = true;
                    } else {
                        console.warn('ID found locally but missing in fetched DOM:', el.id);
                    }
                }
            });

            
            if (updated) {
                window.history.pushState({}, '', url);
            } else {
                console.error('No matching .ajax-content with IDs found in fetched document.');
            }

        } catch (error) {
            console.error('AJAX Table Fetch Error:', error);
            if (e && e.target && e.target.tagName === 'FORM') {
                e.target.submit();
            } else if (targetUrl) {
                window.location.href = targetUrl;
            }
        } finally {
            this.loading = false;
        }
    },

    navigate(e) {
        if (!e) return;
        const link = e.target.closest('a');
        if (link && link.href && !link.href.includes('javascript:') && !link.href.includes('#')) {
            e.preventDefault();
            this.updateList(null, link.href);
        }
    }
});
