// scripts for CEO dashboard charts
// this module will initialize the charts and listen for Livewire events.

// if Chart.js is loaded globally (via CDN or bundle), we can use it directly.
(function() {
    // ensure we don't attach listeners twice
    if (window.__dashboardCeoInit) return;
    window.__dashboardCeoInit = true;

    function initCharts() {
        // Data for Top Performers
        const topPerformers = window.__dashboardTopPerformersData || [];
        const performerLabels = topPerformers.map(p => p.user_name);
        const performerScores = topPerformers.map(p => p.final_score);

        // Top Performers Chart
        const topPerformersCtx = document.getElementById('topPerformersChart');
        if (topPerformersCtx) {
            if (window.topPerformersChartInstance) {
                window.topPerformersChartInstance.destroy();
            }

            window.topPerformersChartInstance = new Chart(topPerformersCtx, {
                type: 'bar',
                data: {
                    labels: performerLabels,
                    datasets: [{
                        label: 'Điểm KPI',
                        data: performerScores,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: 30,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Điểm: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: document.documentElement.classList.contains('dark') ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b'
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b',
                                callback: function(val, index) {
                                    return this.getLabelForValue(val).substring(0, 10) + '...';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Data for Phase Progress – values are stored on window by blade when page renders
        const phaseTotal = window.__dashboardPhaseTotal || 0;
        const phaseActive = window.__dashboardPhaseActive || 0;
        const phaseCompleted = window.__dashboardPhaseCompleted || 0;
        const phasePending = phaseTotal - phaseActive - phaseCompleted;

        const phaseProgressCtx = document.getElementById('phaseProgressChart');
        if (phaseProgressCtx) {
            if (window.phaseProgressChartInstance) {
                window.phaseProgressChartInstance.destroy();
            }
            window.phaseProgressChartInstance = new Chart(phaseProgressCtx, {
                 type: 'doughnut',
                 data: {
                     labels: ['Hoàn thành', 'Đang chạy', 'Chưa bắt đầu'],
                     datasets: [{
                         data: [phaseCompleted, phaseActive, phasePending],
                         backgroundColor: ['#10b981', '#3b82f6', '#94a3b8'],
                         borderWidth: 0,
                         hoverOffset: 4
                     }]
                 },
                 options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     cutout: '75%',
                     plugins: {
                         legend: {
                             position: 'bottom',
                             labels: {
                                 usePointStyle: true,
                                 padding: 20,
                                 color: document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b'
                             }
                         }
                     }
                 },
                 plugins: [{
                     id: 'textCenter',
                     beforeDraw: function(chart) {
                         var width = chart.width,
                             height = chart.height,
                             ctx = chart.ctx;

                         ctx.restore();
                         var fontSize = (height / 100).toFixed(2);
                         ctx.font = "bold " + fontSize + "em sans-serif";
                         ctx.textBaseline = "middle";
                         ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#fff' : '#0f172a';

                         var text = phaseTotal,
                             textX = Math.round((width - ctx.measureText(text).width) / 2),
                             textY = height / 2.2;

                         ctx.fillText(text, textX, textY);

                         var fontSizeLabel = (height / 250).toFixed(2);
                         ctx.font = fontSizeLabel + "em sans-serif";
                         ctx.fillStyle = "#64748b";
                         var label = "Tổng Phase";
                         var labelX = Math.round((width - ctx.measureText(label).width) / 2);
                         var labelY = height / 1.7;
                         ctx.fillText(label, labelX, labelY);

                         ctx.save();
                     }
                 }]
             });
         }
    }

    document.addEventListener('livewire:navigated', initCharts);
    document.addEventListener('DOMContentLoaded', initCharts);
    document.addEventListener('update-top-performers-chart', (event) => {
        const data = (Array.isArray(event.detail) ? event.detail[0] : event.detail);
        window.__dashboardTopPerformersData = data.data;
        if (window.topPerformersChartInstance) {
            const newData = data.data;
            window.topPerformersChartInstance.data.labels = newData.map(p => p.user_name);
            window.topPerformersChartInstance.data.datasets[0].data = newData.map(p => p.final_score);
            window.topPerformersChartInstance.update();
        }
    });

    document.addEventListener('charts-updated', (event) => {
        const data = (Array.isArray(event.detail) ? event.detail[0] : event.detail) || null;
        if (data) {
            if (data.top_performers) {
                window.__dashboardTopPerformersData = data.top_performers;
            }
            if (data.phases) {
                window.__dashboardPhaseTotal = data.phases.total;
                window.__dashboardPhaseActive = data.phases.active;
                window.__dashboardPhaseCompleted = data.phases.completed;
            }
            initCharts();
        }
    });
})();
