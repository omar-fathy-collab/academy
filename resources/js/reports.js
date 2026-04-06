// Chart Configuration
const chartConfigs = {
    revenueChart: {
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('en-EG', {
                                    style: 'currency',
                                    currency: 'EGP'
                                }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('en-EG', {
                                style: 'currency',
                                currency: 'EGP'
                            }).format(value);
                        }
                    }
                }
            }
        }
    },
    groupsChart: {
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right'
                }
            }
        }
    }
};

const chartInstances = {};

AOS.init({
    duration: 800,
    once: true
});

// Function to toggle chart view
window.toggleChartView = function(chartId, viewType) {
    console.log('toggleChartView called with:', chartId, viewType);
    console.log('Available chart instances:', Object.keys(chartInstances));
    console.log('window.monthlyData:', window.monthlyData);

    const chart = chartInstances[chartId];
    if (!chart) {
        console.error('Chart instance not found for:', chartId);
        return;
    }

    let newLabels, newRevenueData, newExpensesData;

    if (viewType === 'monthly') {
        // Monthly data (original data) - will be set from Blade template
        if (window.monthlyData) {
            newLabels = window.monthlyData.labels;
            newRevenueData = window.monthlyData.revenue;
            newExpensesData = window.monthlyData.expenses;
            console.log('Using monthly data:', { newLabels, newRevenueData, newExpensesData });
        } else {
            console.error('window.monthlyData not found');
            return;
        }
    } else if (viewType === 'daily') {
        // Sample daily data for the last 30 days
        const today = new Date();
        newLabels = [];
        newRevenueData = [];
        newExpensesData = [];

        for (let i = 29; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            newLabels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));

            // Generate sample data (in real implementation, this would come from backend)
            const baseRevenue = Math.floor(Math.random() * 5000) + 1000;
            const baseExpenses = Math.floor(Math.random() * 3000) + 500;
            newRevenueData.push(baseRevenue);
            newExpensesData.push(baseExpenses);
        }
        console.log('Generated daily data:', { newLabels: newLabels.length, newRevenueData: newRevenueData.length });
    }

    if (newLabels && newRevenueData && newExpensesData) {
        // Update chart data
        chart.data.labels = newLabels;
        chart.data.datasets[0].data = newRevenueData;
        chart.data.datasets[1].data = newExpensesData;

        // Update chart
        chart.update();

        console.log(`Successfully switched ${chartId} to ${viewType} view`);
    } else {
        console.error('Missing data for chart update');
    }
};




