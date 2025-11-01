/**
 * HW On Sale - Admin Dashboard JavaScript
 * Chart.js initialization and analytics fetching
 */

(function($) {
	'use strict';

	let charts = {
		timeseries: null,
		topProducts: null,
		devices: null
	};

	$(document).ready(function() {
		initializeAnalytics();
		initializeExport();
		initializeDateFilters();
	});

	/**
	 * Initialize analytics
	 */
	function initializeAnalytics() {
		if (!$('#hw-onsale-kpis').length) return;

		loadAnalytics();
	}

	/**
	 * Load analytics data
	 */
	function loadAnalytics() {
		const from = $('#hw-onsale-date-from').val();
		const to = $('#hw-onsale-date-to').val();

		$.ajax({
			url: window.hwOnsaleAdmin.restUrl + '/analytics',
			method: 'GET',
			data: { from: from, to: to },
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', window.hwOnsaleAdmin.nonce);
			},
			success: function(data) {
				updateKPIs(data.kpis);
				renderTimeseriesChart(data.timeseries);
				renderTopProductsChart(data.top_products);
				renderDeviceChart(data.device_breakdown);
			},
			error: function() {
				alert(window.hwOnsaleAdmin.i18n.loadError);
			}
		});
	}

	/**
	 * Update KPI cards
	 */
	function updateKPIs(kpis) {
		$('[data-kpi="views"]').text(kpis.views.toLocaleString());
		$('[data-kpi="clicks"]').text(kpis.clicks.toLocaleString());
		$('[data-kpi="ctr"]').text(kpis.ctr + '%');
		$('[data-kpi="add_to_cart"]').text(kpis.add_to_cart.toLocaleString());
	}

	/**
	 * Render timeseries chart
	 */
	function renderTimeseriesChart(data) {
		const ctx = document.getElementById('hw-onsale-chart-timeseries');
		if (!ctx) return;

		if (charts.timeseries) {
			charts.timeseries.destroy();
		}

		const labels = data.map(d => d.date);
		const views = data.map(d => d.views);
		const clicks = data.map(d => d.clicks);
		const addToCart = data.map(d => d.add_to_cart);

		charts.timeseries = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Views',
						data: views,
						borderColor: '#667eea',
						backgroundColor: 'rgba(102, 126, 234, 0.1)',
						tension: 0.4
					},
					{
						label: 'Clicks',
						data: clicks,
						borderColor: '#f5576c',
						backgroundColor: 'rgba(245, 87, 108, 0.1)',
						tension: 0.4
					},
					{
						label: 'Add to Cart',
						data: addToCart,
						borderColor: '#43e97b',
						backgroundColor: 'rgba(67, 233, 123, 0.1)',
						tension: 0.4
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: {
						position: 'top'
					},
					tooltip: {
						mode: 'index',
						intersect: false
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0
						}
					}
				}
			}
		});

		// Accessible summary
		const totalViews = views.reduce((a, b) => a + b, 0);
		const totalClicks = clicks.reduce((a, b) => a + b, 0);
		const totalATC = addToCart.reduce((a, b) => a + b, 0);
		$('#chart-timeseries-summary').text(
			`Time series chart showing ${totalViews} total views, ${totalClicks} total clicks, and ${totalATC} add to cart actions over the selected period.`
		);
	}

	/**
	 * Render top products chart
	 */
	function renderTopProductsChart(data) {
		const ctx = document.getElementById('hw-onsale-chart-top-products');
		if (!ctx) return;

		if (charts.topProducts) {
			charts.topProducts.destroy();
		}

		const labels = data.map(d => d.name);
		const clicks = data.map(d => d.clicks);

		charts.topProducts = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					label: 'Clicks',
					data: clicks,
					backgroundColor: 'rgba(102, 126, 234, 0.8)',
					borderColor: '#667eea',
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				indexAxis: 'y',
				plugins: {
					legend: {
						display: false
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						ticks: {
							precision: 0
						}
					}
				}
			}
		});

		// Accessible summary
		if (data.length > 0) {
			const topProduct = data[0];
			$('#chart-top-products-summary').text(
				`Top products chart showing ${data.length} products. The top product is "${topProduct.name}" with ${topProduct.clicks} clicks.`
			);
		}
	}

	/**
	 * Render device breakdown chart
	 */
	function renderDeviceChart(data) {
		const ctx = document.getElementById('hw-onsale-chart-devices');
		if (!ctx) return;

		if (charts.devices) {
			charts.devices.destroy();
		}

		const labels = data.map(d => d.device.charAt(0).toUpperCase() + d.device.slice(1));
		const counts = data.map(d => d.count);

		charts.devices = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: counts,
					backgroundColor: [
						'rgba(102, 126, 234, 0.8)',
						'rgba(245, 87, 108, 0.8)',
						'rgba(67, 233, 123, 0.8)'
					],
					borderColor: [
						'#667eea',
						'#f5576c',
						'#43e97b'
					],
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: {
						position: 'bottom'
					}
				}
			}
		});

		// Accessible summary
		const total = counts.reduce((a, b) => a + b, 0);
		const breakdown = labels.map((label, i) => {
			const percent = total > 0 ? Math.round((counts[i] / total) * 100) : 0;
			return `${label}: ${percent}%`;
		}).join(', ');
		$('#chart-devices-summary').text(
			`Device breakdown chart showing: ${breakdown}`
		);
	}

	/**
	 * Initialize export
	 */
	function initializeExport() {
		$('#hw-onsale-export').on('click', function() {
			const from = $('#hw-onsale-date-from').val();
			const to = $('#hw-onsale-date-to').val();

			$.ajax({
				url: window.hwOnsaleAdmin.restUrl + '/export',
				method: 'GET',
				data: { from: from, to: to },
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.hwOnsaleAdmin.nonce);
				},
				success: function(data) {
					downloadCSV(data.csv, data.filename);
					alert(window.hwOnsaleAdmin.i18n.exportSuccess);
				},
				error: function() {
					alert(window.hwOnsaleAdmin.i18n.exportError);
				}
			});
		});
	}

	/**
	 * Download CSV
	 */
	function downloadCSV(csv, filename) {
		const blob = new Blob([csv], { type: 'text/csv' });
		const url = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		window.URL.revokeObjectURL(url);
	}

	/**
	 * Initialize date filters
	 */
	function initializeDateFilters() {
		$('#hw-onsale-refresh').on('click', function() {
			loadAnalytics();
		});
	}

})(jQuery);
