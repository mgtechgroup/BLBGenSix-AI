/**
 * SVG Charting Library - Pure vanilla JS SVG-based charts
 * ES5-compatible, no external dependencies
 */
var Charts = (function() {
    var NS = "http://www.w3.org/2000/svg";
    var COLORS = ["#8B5CF6","#EC4899","#10B981","#F59E0B","#3B82F6","#EF4444","#06B6D4","#F97316","#6366F1","#14B8A6"];
    
    function createSVG(width, height) {
        var svg = document.createElementNS(NS, "svg");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");
        svg.setAttribute("viewBox", "0 0 " + width + " " + height);
        svg.setAttribute("preserveAspectRatio", "none");
        return svg;
    }
    
    function createEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        for (var key in attrs) { el.setAttribute(key, attrs[key]); }
        return el;
    }
    
    function tooltip(el, text) {
        el.addEventListener("mouseenter", function(e) {
            var tip = document.createElement("div");
            tip.className = "chart-tooltip";
            tip.textContent = text;
            tip.style.cssText = "position:fixed;background:#1a1a22;border:1px solid #333;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;z-index:9999;pointer-events:none;";
            document.body.appendChild(tip);
            var rect = el.getBoundingClientRect();
            tip.style.left = (rect.left + rect.width/2 - tip.offsetWidth/2) + "px";
            tip.style.top = (rect.top - tip.offsetHeight - 8) + "px";
            el._tip = tip;
        });
        el.addEventListener("mouseleave", function() {
            if (el._tip) { el._tip.remove(); el._tip = null; }
        });
    }
    
    // Sparkline - minimal inline chart
    function sparkline(container, data, color, width, height) {
        color = color || "#8B5CF6";
        width = width || 200;
        height = height || 40;
        container.innerHTML = "";
        var svg = createSVG(width, height);
        if (!data || data.length < 2) return;
        var min = Math.min.apply(null, data);
        var max = Math.max.apply(null, data);
        var range = max - min || 1;
        var stepX = width / (data.length - 1);
        var points = [];
        for (var i = 0; i < data.length; i++) {
            var x = i * stepX;
            var y = height - ((data[i] - min) / range) * (height - 4) - 2;
            points.push(x + "," + y);
        }
        var polyline = createEl("polyline", { points: points.join(" "), fill: "none", stroke: color, "stroke-width": "2", "stroke-linecap": "round", "stroke-linejoin": "round" });
        svg.appendChild(polyline);
        var areaPoints = "0," + height + " " + points.join(" ") + " " + width + "," + height;
        var area = createEl("polygon", { points: areaPoints, fill: color, opacity: "0.1" });
        svg.appendChild(area);
        var lastX = (data.length - 1) * stepX;
        var lastY = height - ((data[data.length-1] - min) / range) * (height - 4) - 2;
        var dot = createEl("circle", { cx: lastX, cy: lastY, r: "3", fill: color });
        tooltip(dot, data[data.length-1].toString());
        svg.appendChild(dot);
        container.appendChild(svg);
    }
    
    // Bar Chart
    function barChart(container, datasets, options) {
        options = options || {};
        container.innerHTML = "";
        var width = options.width || 600;
        var height = options.height || 300;
        var barRadius = options.barRadius || 4;
        var showValues = options.showValues !== false;
        var showLegend = options.showLegend !== false;
        var horizontal = options.horizontal || false;
        
        var svg = createSVG(width, height);
        var margin = { top: 30, right: 20, bottom: 50, left: 60 };
        var chartW = width - margin.left - margin.right;
        var chartH = height - margin.top - margin.bottom;
        var groupLabels = (datasets[0] && datasets[0].labels) || [];
        var groups = groupLabels.length || datasets[0].data.length;
        var barsPerGroup = datasets.length;
        var barWidth = Math.min(40, (chartW / groups) * 0.7 / barsPerGroup);
        var groupWidth = chartW / groups;
        
        var allValues = [];
        for (var di = 0; di < datasets.length; di++) {
            var d = datasets[di];
            for (var vi = 0; vi < d.data.length; vi++) { allValues.push(d.data[vi]); }
        }
        var maxVal = Math.max.apply(null, allValues);
        if (maxVal < 1) maxVal = 1;
        var allMin = Math.min.apply(null, allValues);
        if (allMin > 0) allMin = 0;
        var valRange = maxVal - allMin || 1;
        
        // Grid lines
        var gridCount = 5;
        for (var gi = 0; gi <= gridCount; gi++) {
            var y = margin.top + (chartH * gi / gridCount);
            var line = createEl("line", { x1: margin.left, y1: y, x2: width - margin.right, y2: y, stroke: "#222", "stroke-width": "1" });
            svg.appendChild(line);
            var val = allMin + (valRange * (gridCount - gi) / gridCount);
            var text = createEl("text", { x: margin.left - 8, y: y + 4, fill: "#666", "font-size": "10", "text-anchor": "end" });
            text.textContent = Math.round(val).toLocaleString();
            svg.appendChild(text);
        }
        
        // Bars
        for (var di2 = 0; di2 < datasets.length; di2++) {
            var dataset = datasets[di2];
            for (var bi = 0; bi < dataset.data.length; bi++) {
                var val2 = dataset.data[bi];
                var barH = (val2 / valRange) * chartH;
                var x = margin.left + (bi * groupWidth) + (di2 * barWidth) + (groupWidth - barsPerGroup * barWidth) / 2;
                var y = margin.top + chartH - barH;
                var rect = createEl("rect", { x: x, y: y, width: barWidth - 2, height: barH, fill: dataset.color || COLORS[di2], rx: barRadius, ry: barRadius });
                tooltip(rect, dataset.label + ": " + val2);
                svg.appendChild(rect);
                if (showValues && barH > 15) {
                    var valText = createEl("text", { x: x + (barWidth - 2) / 2, y: y - 4, fill: "#999", "font-size": "9", "text-anchor": "middle" });
                    valText.textContent = val2;
                    svg.appendChild(valText);
                }
            }
        }
        
        // X-axis labels
        if (groupLabels.length > 0) {
            for (var li = 0; li < groupLabels.length; li++) {
                var labelX = margin.left + (li * groupWidth) + groupWidth / 2;
                var label = createEl("text", { x: labelX, y: height - 10, fill: "#666", "font-size": "10", "text-anchor": "middle" });
                label.textContent = groupLabels[li];
                svg.appendChild(label);
            }
        }
        
        // Legend
        if (showLegend && datasets.length > 1) {
            var legendY = height - 30;
            var legendX = margin.left;
            for (var li2 = 0; li2 < datasets.length; li2++) {
                var l = datasets[li2];
                var colorBox = createEl("rect", { x: legendX, y: legendY, width: 12, height: 12, fill: l.color || COLORS[li2] });
                svg.appendChild(colorBox);
                var legendText = createEl("text", { x: legendX + 16, y: legendY + 10, fill: "#999", "font-size": "10" });
                legendText.textContent = l.label;
                svg.appendChild(legendText);
                legendX += 16 + l.label.length * 6 + 20;
            }
        }
        
        container.appendChild(svg);
    }
    
    // Line Chart
    function lineChart(container, datasets, options) {
        options = options || {};
        container.innerHTML = "";
        var width = options.width || 600;
        var height = options.height || 300;
        
        var svg = createSVG(width, height);
        var margin = { top: 30, right: 20, bottom: 50, left: 60 };
        var chartW = width - margin.left - margin.right;
        var chartH = height - margin.top - margin.bottom;
        
        var allValues = [];
        for (var di = 0; di < datasets.length; di++) {
            for (var vi = 0; vi < datasets[di].data.length; vi++) { allValues.push(datasets[di].data[vi]); }
        }
        var maxVal = Math.max.apply(null, allValues) || 1;
        var minVal = Math.min.apply(null, allValues) || 0;
        var range = maxVal - minVal || 1;
        
        // Grid
        for (var gi = 0; gi <= 5; gi++) {
            var y = margin.top + (chartH * gi / 5);
            var line = createEl("line", { x1: margin.left, y1: y, x2: width - margin.right, y2: y, stroke: "#222", "stroke-width": "1" });
            svg.appendChild(line);
            var val = minVal + (range * (5 - gi) / 5);
            var text = createEl("text", { x: margin.left - 8, y: y + 4, fill: "#666", "font-size": "10", "text-anchor": "end" });
            text.textContent = Math.round(val);
            svg.appendChild(text);
        }
        
        // Lines
        for (var di2 = 0; di2 < datasets.length; di2++) {
            var dataset = datasets[di2];
            var points = [];
            var stepX = chartW / (dataset.data.length - 1);
            for (var pi = 0; pi < dataset.data.length; pi++) {
                var x = margin.left + (pi * stepX);
                var y2 = margin.top + chartH - ((dataset.data[pi] - minVal) / range) * chartH;
                points.push(x + "," + y2);
                var dot = createEl("circle", { cx: x, cy: y2, r: "3", fill: dataset.color || COLORS[di2], opacity: "0" });
                tooltip(dot, dataset.label + ": " + dataset.data[pi]);
                dot.addEventListener("mouseenter", function() { this.setAttribute("opacity", "1"); });
                dot.addEventListener("mouseleave", function() { this.setAttribute("opacity", "0"); });
                svg.appendChild(dot);
            }
            var polyline = createEl("polyline", { points: points.join(" "), fill: "none", stroke: dataset.color || COLORS[di2], "stroke-width": "2", "stroke-linecap": "round", "stroke-linejoin": "round" });
            svg.appendChild(polyline);
        }
        
        container.appendChild(svg);
    }
    
    // Pie Chart
    function pieChart(container, data, options) {
        options = options || {};
        container.innerHTML = "";
        var width = options.width || 300;
        var height = options.height || 300;
        var radius = Math.min(width, height) / 2 - 20;
        
        var svg = createSVG(width, height);
        var cx = width / 2;
        var cy = height / 2;
        
        var total = 0;
        for (var i = 0; i < data.length; i++) { total += data[i].value; }
        if (total === 0) total = 1;
        
        var startAngle = -Math.PI / 2;
        for (var i2 = 0; i2 < data.length; i2++) {
            var d = data[i2];
            var sliceAngle = (d.value / total) * 2 * Math.PI;
            var endAngle = startAngle + sliceAngle;
            var x1 = cx + radius * Math.cos(startAngle);
            var y1 = cy + radius * Math.sin(startAngle);
            var x2 = cx + radius * Math.cos(endAngle);
            var y2 = cy + radius * Math.sin(endAngle);
            var largeArc = sliceAngle > Math.PI ? 1 : 0;
            var path = "M " + cx + " " + cy + " L " + x1 + " " + y1 + " A " + radius + " " + radius + " 0 " + largeArc + " 1 " + x2 + " " + y2 + " Z";
            var slice = createEl("path", { d: path, fill: d.color || COLORS[i2], stroke: "#0a0a12", "stroke-width": "2" });
            tooltip(slice, d.label + ": " + d.value + " (" + Math.round((d.value/total)*100) + "%)");
            svg.appendChild(slice);
            startAngle = endAngle;
        }
        
        container.appendChild(svg);
    }
    
    // Scatter Chart
    function scatterChart(container, datasets, options) {
        options = options || {};
        container.innerHTML = "";
        var width = options.width || 600;
        var height = options.height || 300;
        var svg = createSVG(width, height);
        var margin = { top: 30, right: 20, bottom: 50, left: 60 };
        var chartW = width - margin.left - margin.right;
        var chartH = height - margin.top - margin.bottom;
        container.appendChild(svg);
    }
    
    // Dependency Graph
    function dependencyGraph(container, nodes, edges, options) {
        options = options || {};
        container.innerHTML = "";
        var width = options.width || 600;
        var height = options.height || 400;
        var svg = createSVG(width, height);
        container.appendChild(svg);
    }
    
    return {
        sparkline: sparkline,
        barChart: barChart,
        lineChart: lineChart,
        pieChart: pieChart,
        scatterChart: scatterChart,
        dependencyGraph: dependencyGraph
    };
})();
