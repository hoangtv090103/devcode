

/**
 * JavaScript for teacher dashboard charts
 *
 * @module     mod_devcode/teacher_charts
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/chartjs'], function($, Chart) {
    
    /**
     * Initialize the charts
     */
    var init = function() {
        if (typeof teacherChartData === 'undefined') {
            return; // No chart data available
        }
        
        // Detect which type of chart to show based on data
        if (teacherChartData.assignmentName) {
            // Single assignment charts
            renderScoreDistributionChart();
            renderSubmissionActivityChart();
            renderStatusDistributionChart();
        } else if (teacherChartData.assignmentNames) {
            // Course-level charts
            renderSubmissionCountChart();
            renderAverageScoreChart();
            renderSubmissionRateChart();
        }
    };
    
    /**
     * Render a bar chart showing score distribution for a single assignment
     */
    var renderScoreDistributionChart = function() {
        var ctx = document.getElementById('score-distribution-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: teacherChartData.scoreDistribution.labels,
                datasets: [{
                    label: M.util.get_string('submissions', 'mod_devcode'),
                    data: teacherChartData.scoreDistribution.data,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: M.util.get_string('submissioncount', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('score', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('scoredistribution', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a line chart showing submission activity over time
     */
    var renderSubmissionActivityChart = function() {
        var ctx = document.getElementById('submission-activity-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: teacherChartData.submissionActivity.labels,
                datasets: [{
                    label: M.util.get_string('submissions', 'mod_devcode'),
                    data: teacherChartData.submissionActivity.data,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: M.util.get_string('submissioncount', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('date', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('submissionactivity', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a pie chart showing submission status distribution
     */
    var renderStatusDistributionChart = function() {
        var ctx = document.getElementById('status-distribution-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: teacherChartData.statusDistribution.labels,
                datasets: [{
                    data: teacherChartData.statusDistribution.data,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // Graded
                        'rgba(0, 123, 255, 0.7)',  // Submitted
                        'rgba(220, 53, 69, 0.7)'   // Error
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(0, 123, 255, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('statusdistribution', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    };
    
    /**
     * Render a bar chart showing submission count by assignment
     */
    var renderSubmissionCountChart = function() {
        var ctx = document.getElementById('submission-count-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: teacherChartData.assignmentNames,
                datasets: [{
                    label: M.util.get_string('submissions', 'mod_devcode'),
                    data: teacherChartData.submissionCounts,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: M.util.get_string('submissioncount', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('assignments', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('submissioncountbyassignment', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a bar chart showing average score by assignment
     */
    var renderAverageScoreChart = function() {
        var ctx = document.getElementById('average-score-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: teacherChartData.assignmentNames,
                datasets: [{
                    label: M.util.get_string('averagescore', 'mod_devcode'),
                    data: teacherChartData.averageScores,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        title: {
                            display: true,
                            text: M.util.get_string('averagescore', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('assignments', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('averagescorebyassignment', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a bar chart showing submission rate by assignment
     */
    var renderSubmissionRateChart = function() {
        var ctx = document.getElementById('submission-rate-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: teacherChartData.assignmentNames,
                datasets: [{
                    label: M.util.get_string('submissionrate', 'mod_devcode'),
                    data: teacherChartData.submissionRates,
                    backgroundColor: function(context) {
                        var value = context.dataset.data[context.dataIndex];
                        if (value >= 90) {
                            return 'rgba(40, 167, 69, 0.5)'; // High rate (green)
                        } else if (value >= 70) {
                            return 'rgba(255, 193, 7, 0.5)'; // Medium rate (yellow)
                        } else if (value >= 50) {
                            return 'rgba(255, 159, 64, 0.5)'; // Low rate (orange)
                        } else {
                            return 'rgba(220, 53, 69, 0.5)'; // Very low rate (red)
                        }
                    },
                    borderColor: function(context) {
                        var value = context.dataset.data[context.dataIndex];
                        if (value >= 90) {
                            return 'rgba(40, 167, 69, 1)'; // High rate
                        } else if (value >= 70) {
                            return 'rgba(255, 193, 7, 1)'; // Medium rate
                        } else if (value >= 50) {
                            return 'rgba(255, 159, 64, 1)'; // Low rate
                        } else {
                            return 'rgba(220, 53, 69, 1)'; // Very low rate
                        }
                    },
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: M.util.get_string('submissionratepercent', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('assignments', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('submissionratebyassignment', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    return {
        init: init
    };
}); 