// Reports Module JavaScript

// Chart.js Global Configuration
Chart.defaults.color = '#5a5c69';
Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 4;
Chart.defaults.plugins.tooltip.titleMarginBottom = 6;

// Chart Animation Configuration
const chartAnimationConfig = {
    duration: 1000,
    easing: 'easeInOutQuart'
};

// Utility Functions
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-EG', {
        style: 'currency',
        currency: 'EGP'
    }).format(value);
};

const formatPercentage = (value) => {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(value / 100);
};

// Chart Colors
const chartColors = {
    primary: '#4e73df',
    success: '#1cc88a',
    info: '#36b9cc',
    warning: '#f6c23e',
    danger: '#e74a3b',
    primaryLight: 'rgba(78, 115, 223, 0.1)',
    successLight: 'rgba(28, 200, 138, 0.1)',
    infoLight: 'rgba(54, 185, 204, 0.1)',
    warningLight: 'rgba(246, 194, 62, 0.1)',
    dangerLight: 'rgba(231, 74, 59, 0.1)'
};

// Export Functions
function exportChart(chartId) {
    const chart = document.getElementById(chartId);
    html2canvas(chart).then(canvas => {
        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.download = `${chartId}.png`;
        link.href = canvas.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}

// AJAX Functions
function loadChartData(url, params = {}) {
    return new Promise((resolve, reject) => {
        fetch(url + '?' + new URLSearchParams(params))
            .then(response => response.json())
            .then(data => resolve(data))
            .catch(error => reject(error));
    });
}

// Date Range Functions
function updateDateRange(period) {
    const today = new Date();
    let startDate = new Date();

    switch(period) {
        case 'daily':
            startDate.setDate(today.getDate() - 30);
            break;
        case 'weekly':
            startDate.setDate(today.getDate() - 90);
            break;
        case 'monthly':
            startDate.setDate(today.getDate() - 365);
            break;
        case 'yearly':
            startDate.setFullYear(today.getFullYear() - 5);
            break;
    }

    document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Period selector change handler
    const periodSelect = document.getElementById('period');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            updateDateRange(this.value);
        });
    }

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
});

// Chart Interactivity
function addChartHover(chart) {
    const chartElement = document.getElementById(chart.canvas.id);
    
    chartElement.addEventListener('mousemove', (e) => {
        const activePoints = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        chartElement.style.cursor = activePoints.length ? 'pointer' : 'default';
    });
}

// Loading State Management
function showLoading(element) {
    element.classList.add('loading');
}

function hideLoading(element) {
    element.classList.remove('loading');
}

// Report-specific Functions
function updateFinancialReport(startDate, endDate, period) {
    const container = document.querySelector('.container-fluid');
    showLoading(container);

    loadChartData('/reports/financial/data', { start_date: startDate, end_date: endDate, period })
        .then(data => {
            // Update all financial charts with new data
            updateRevenueChart(data.revenue);
            updateExpensesChart(data.expenses);
            updateProfitChart(data.profit);
            hideLoading(container);
        })
        .catch(error => {
            console.error('Error loading financial data:', error);
            hideLoading(container);
            alert('Error loading financial data. Please try again.');
        });
}

function updateStudentReport(startDate, endDate, period) {
    const container = document.querySelector('.container-fluid');
    showLoading(container);

    loadChartData('/reports/students/data', { start_date: startDate, end_date: endDate, period })
        .then(data => {
            // Update all student charts with new data
            updateEnrollmentChart(data.enrollment);
            updatePerformanceChart(data.performance);
            updateCoursePopularityChart(data.courses);
            hideLoading(container);
        })
        .catch(error => {
            console.error('Error loading student data:', error);
            hideLoading(container);
            alert('Error loading student data. Please try again.');
        });
}

function updateQuizReport(startDate, endDate, period) {
    const container = document.querySelector('.container-fluid');
    showLoading(container);

    loadChartData('/reports/quizzes/data', { start_date: startDate, end_date: endDate, period })
        .then(data => {
            // Update all quiz charts with new data
            updateQuizPerformanceChart(data.performance);
            updateScoreDistributionChart(data.distribution);
            updateTrendChart(data.trends);
            hideLoading(container);
        })
        .catch(error => {
            console.error('Error loading quiz data:', error);
            hideLoading(container);
            alert('Error loading quiz data. Please try again.');
        });
}

// Export Functions
async function exportToPowerPoint() {
    const container = document.querySelector('.container-fluid');
    showLoading(container);

    try {
        const charts = document.querySelectorAll('canvas');
        const images = await Promise.all(Array.from(charts).map(async chart => {
            return {
                id: chart.id,
                data: await html2canvas(chart).then(canvas => canvas.toDataURL('image/png'))
            };
        }));

        // Send to server for PowerPoint generation
        const response = await fetch('/reports/export/powerpoint', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                charts: images,
                filters: {
                    startDate: document.getElementById('start_date').value,
                    endDate: document.getElementById('end_date').value,
                    period: document.getElementById('period').value
                }
            })
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'report.pptx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            throw new Error('Failed to generate PowerPoint');
        }
    } catch (error) {
        console.error('Error exporting to PowerPoint:', error);
        alert('Error exporting to PowerPoint. Please try again.');
    } finally {
        hideLoading(container);
    }
}

// Initialize Charts with Animation
function initializeChartAnimations(chart) {
    const originalDraw = chart.draw;
    
    chart.draw = function() {
        originalDraw.apply(this, arguments);
        
        const ctx = this.ctx;
        const height = this.height;
        
        ctx.save();
        ctx.globalCompositeOperation = 'destination-out';
        
        let currentFrame = 0;
        const numFrames = 60;
        
        const animate = () => {
            currentFrame++;
            
            const linearProgress = currentFrame / numFrames;
            const easedProgress = 1 - Math.pow(1 - linearProgress, 3); // Cubic easing
            
            ctx.fillRect(0, height * easedProgress, this.width, height);
            
            if (currentFrame < numFrames) {
                requestAnimationFrame(animate);
            } else {
                ctx.restore();
            }
        };
        
        animate();
    };
}