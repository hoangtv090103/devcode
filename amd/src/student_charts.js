// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for student dashboard charts
 *
 * @module     mod_devcode/student_charts
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/chartjs'], function($, Chart) {
    
    /**
     * Initialize the charts
     */
    var init = function() {
        if (typeof chartData === 'undefined') {
            return; // No chart data available
        }
        
        // Detect which type of chart to show based on data
        if (chartData.assignmentName) {
            // For single assignment progress chart
            renderProgressChart();
        } else if (chartData.assignmentNames) {
            // For course-level charts
            renderScoresChart();
            renderAttemptsChart();
        }
    };
    
    /**
     * Render a line chart showing submission progress for a single assignment
     */
    var renderProgressChart = function() {
        var ctx = document.getElementById('progress-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: M.util.get_string('score', 'mod_devcode'),
                    data: chartData.scores,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.1
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
                            text: M.util.get_string('score', 'mod_devcode')
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: M.util.get_string('attempts', 'mod_devcode')
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: M.util.get_string('progresschart', 'mod_devcode') + ': ' + chartData.assignmentName,
                        font: {
                            size: 16
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return M.util.get_string('attempt', 'mod_devcode') + ' ' + (context[0].dataIndex + 1);
                            }
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a bar chart showing best scores for each assignment
     */
    var renderScoresChart = function() {
        var ctx = document.getElementById('scores-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.assignmentNames,
                datasets: [{
                    label: M.util.get_string('bestscore', 'mod_devcode'),
                    data: chartData.bestScores,
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
                            text: M.util.get_string('score', 'mod_devcode')
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
                        text: M.util.get_string('bestscorebyassignment', 'mod_devcode'),
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    };
    
    /**
     * Render a bar chart showing submission attempts for each assignment
     */
    var renderAttemptsChart = function() {
        var ctx = document.getElementById('attempts-chart');
        if (!ctx) return;
        
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.assignmentNames,
                datasets: [{
                    label: M.util.get_string('attempts', 'mod_devcode'),
                    data: chartData.submissionCounts,
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
                            text: M.util.get_string('attemptcount', 'mod_devcode')
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
                        text: M.util.get_string('attemptsbyassignment', 'mod_devcode'),
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