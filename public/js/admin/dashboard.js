/*
|------------------------------------------------------------------------------
| Dashboard charts
|------------------------------------------------------------------------------
| ApexCharts comes with Metronic's plugin bundle. Nothing is loaded here.
|
| This file DRAWS. It does not calculate, aggregate, format money or decide what
| anything means — every series and every tooltip string arrives from
| DashboardService already finished. That is the same rule the rest of the panel
| follows, and it is why each card has a table tab: the table is the canonical
| rendering, straight from Blade, and the chart is a second view of it. If the
| two ever disagree, believe the table.
|
| Charts are drawn LAZILY, when their tab is first shown. ApexCharts measures
| its container to size itself, and a container inside a hidden Bootstrap tab
| has a width of zero — draw it there and it renders one pixel wide and stays
| that way. The first two are visible on load; the rest wait for their tab.
*/
(function () {
    'use strict';

    var data = window.DashboardCharts;

    if (!data || typeof window.ApexCharts === 'undefined') {
        return;
    }

    /*
    | Colours read from Metronic's own CSS variables rather than hardcoded, so
    | the charts follow a theme change instead of staying light-mode blue on a
    | dark background.
    */
    function themeColour(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name);
        return (value || '').trim() || fallback;
    }

    var colours = {
        primary: themeColour('--bs-primary', '#009ef7'),
        success: themeColour('--bs-success', '#50cd89'),
        warning: themeColour('--bs-warning', '#ffc700'),
        info: themeColour('--bs-info', '#7239ea'),
        muted: themeColour('--bs-gray-500', '#a1a5b7'),
        gridLine: themeColour('--bs-gray-200', '#f1f1f4'),
        label: themeColour('--bs-gray-600', '#7e8299')
    };

    var baseAxis = {
        labels: { style: { colors: colours.label, fontSize: '11px' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
    };

    /* =====================================================================
       Requests and confirmations
       ===================================================================== */

    function trendChart(el) {
        return new ApexCharts(el, {
            chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Requested', data: data.trend.requested },
                { name: 'Confirmed', data: data.trend.confirmed }
            ],
            colors: [colours.info, colours.success],
            xaxis: Object.assign({ categories: data.trend.labels }, baseAxis),

            // Whole bookings. Without this Apex offers "2.5 requests" on a
            // quiet week, which is not a thing.
            yaxis: Object.assign({}, baseAxis, {
                labels: { style: { colors: colours.label }, formatter: function (v) { return Math.round(v); } }
            }),

            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: colours.gridLine, strokeDashArray: 4 },
            legend: { labels: { colors: colours.label } },
            tooltip: { theme: 'light' }
        });
    }

    /* =====================================================================
       Money received
       ===================================================================== */

    function revenueChart(el) {
        return new ApexCharts(el, {
            chart: { type: 'bar', height: 300, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Cash', data: data.revenue.cash },
                { name: 'Settled by voucher', data: data.revenue.voucher }
            ],
            colors: [colours.success, colours.muted],
            xaxis: Object.assign({ categories: data.revenue.labels }, baseAxis),

            /*
             | Axis labels are abbreviated — 12k rather than 12,000 — because
             | a six-figure axis label eats a third of the plot width. This is
             | the ONE piece of number formatting in the browser, and it is
             | deliberately not currency: the exact figures are in the tooltip
             | and in the table tab, both formatted by PHP.
             */
            yaxis: Object.assign({}, baseAxis, {
                labels: {
                    style: { colors: colours.label },
                    formatter: function (v) {
                        return v >= 1000 ? Math.round(v / 1000) + 'k' : Math.round(v);
                    }
                }
            }),

            plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
            dataLabels: { enabled: false },
            grid: { borderColor: colours.gridLine, strokeDashArray: 4 },
            legend: { labels: { colors: colours.label } },

            tooltip: {
                theme: 'light',
                y: {
                    formatter: function (value, opts) {
                        // The server-built string, not a locale call here.
                        var row = data.revenue.tooltips[opts.dataPointIndex];
                        if (!row) return value;
                        return opts.seriesIndex === 0 ? row.cash : row.voucher;
                    }
                }
            }
        });
    }

    /* =====================================================================
       What people book
       ===================================================================== */

    function workshopsChart(el) {
        return new ApexCharts(el, {
            // Horizontal bars, not a pie. Workshop titles are long enough that
            // a pie legend takes more room than the chart, and a ranking is the
            // question anyway — pies are bad at ordering.
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Bookings', data: data.workshops.counts }],
            colors: [colours.primary],
            xaxis: Object.assign({ categories: data.workshops.labels }, baseAxis),
            yaxis: Object.assign({}, baseAxis),
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            dataLabels: { enabled: true, style: { colors: ['#fff'], fontSize: '11px' } },
            grid: { borderColor: colours.gridLine, strokeDashArray: 4 },
            legend: { show: false },
            tooltip: { theme: 'light' }
        });
    }

    /* =====================================================================
       Drawing, once each, when visible
       ===================================================================== */

    var builders = {
        'chart-trend': trendChart,
        'chart-revenue': revenueChart,
        'chart-workshops': workshopsChart
    };

    var drawn = {};

    function draw(id) {
        if (drawn[id]) return;

        var el = document.getElementById(id);
        if (!el || !builders[id]) return;

        // Zero width means the tab is still hidden. Leave it undrawn; the tab's
        // shown event will call back.
        if (el.offsetWidth === 0) return;

        drawn[id] = true;
        builders[id](el).render();
    }

    Object.keys(builders).forEach(draw);

    /*
    | Bootstrap fires shown.bs.tab after the pane has its final size, which is
    | the moment a chart can measure itself correctly. Delegated from the
    | document so the three cards need no individual wiring.
    */
    document.addEventListener('shown.bs.tab', function (event) {
        var target = event.target.getAttribute('href');
        if (!target) return;

        var pane = document.querySelector(target);
        if (!pane) return;

        Object.keys(builders).forEach(function (id) {
            if (pane.querySelector('#' + id)) draw(id);
        });
    });

    /*
    | A collapsed sidebar or a resized window changes the available width. Apex
    | handles its own resize, but a chart that was never drawn because its
    | container was zero-width needs another attempt.
    */
    var resizeTimer = null;

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            Object.keys(builders).forEach(draw);
        }, 250);
    });
})();
