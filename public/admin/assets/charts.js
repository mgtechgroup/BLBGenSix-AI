/**
 * SVG Charting Library - Pure vanilla JS SVG-based charts
 * No external dependencies
 */
const Charts = (() => {
    const NS = 'http://www.w3.org/2000/svg';
    const COLORS = ['#8B5CF6','#EC4899','#10B981','#F59E0B','#3B82F6','#EF4444','#06B6D4','#F97316','#6366F1','#14B8A6'];

    function createSVG(width, height) {
        const svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        return svg;
    }

    function createEl(tag, attrs) {
        const el = document.createElementNS(NS, tag);
        Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
        return el;
    }

    function tooltip(el, text) {
        el.addEventListener('mouseenter', (e) => {
            const tip = document.createElement('div');
            tip.className = 'chart-tooltip';
            tip.textContent = text;
            tip.style.cssText = 'position:fixed;background:#1a1a22;border:1px solid #333;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;z-index:9999;pointer-events:none;';
            document.body.appendChild(tip);
            const rect = el.getBoundingClientRect();
            tip.style.left = (rect.left + rect.width/2 - tip.offsetWidth/2) + 'px';
            tip.style.top = (rect.top - tip.offsetHeight - 8) + 'px';
            el._tip = tip;
        });
        el.addEventListener('mouseleave', () => {
            if (el._tip) { el._tip.remove(); el._tip = null; }
        });
    }

    // Sparkline - minimal inline chart
    function sparkline(container, data, color = '#8B5CF6', width = 200, height = 40) {
        container.innerHTML = '';
        const svg = createSVG(width, height);
        if (!data || data.length < 2) return;
        const min = Math.min(...data);
        const max = Math.max(...data);
        const range = max - min || 1;
        const stepX = width / (data.length - 1);
        const points = data.map((v, i) => {
            const x = i * stepX;
            const y = height - ((v - min) / range) * (height - 4) - 2;
            return `${x},${y}`;
        }).join(' ');
        const line = createEl('polyline', { points, fill: 'none', stroke: color, 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
        const areaPoints = `0,${height} ` + points + ` ${width},${height}`;
        const area = createEl('polygon', { points: areaPoints, fill: color, opacity: '0.1' });
        svg.appendChild(area);
        svg.appendChild(line);
        // Last dot
        const lastX = (data.length - 1) * stepX;
        const lastY = height - ((data[data.length-1] - min) / range) * (height - 4) - 2;
        const dot = createEl('circle', { cx: lastX, cy: lastY, r: '3', fill: color });
        tooltip(dot, data[data.length-1].toString());
        svg.appendChild(dot);
        container.appendChild(svg);
    }

    // Bar Chart
    function barChart(container, datasets, options = {}) {
        container.innerHTML = '';
        const { width = 600, height = 300, barRadius = 4, showValues = true, showLegend = true, horizontal = false } = options;
        const svg = createSVG(width, height);
        const margin = { top: 30, right: 20, bottom: 50, left: 60 };
        const chartW = width - margin.left - margin.right;
        const chartH = height - margin.top - margin.bottom;
        const groupLabels = datasets[0]?.labels || [];
        const groups = groupLabels.length;
        const barsPerGroup = datasets.length;
        const barWidth = Math.min(40, (chartW / groups) * 0.7 / barsPerGroup);
        const groupWidth = chartW / groups;
        const allValues = datasets.flatMap(d => d.data);
        const maxVal = Math.max(...allValues, 1);
        const allMin = Math.min(...allValues, 0);
        const valRange = maxVal - allMin || 1;

        // Grid lines
        const gridCount = 5;
        for (let i = 0; i <= gridCount; i++) {
            const y = margin.top + (chartH * i / gridCount);
            const line = createEl('line', { x1: margin.left, y1: y, x2: width - margin.right, y2: y, stroke: '#222', 'stroke-width': '1' });
            svg.appendChild(line);
            const val = allMin + (valRange * (gridCount - i) / gridCount);
            const text = createEl('text', { x: margin.left - 8, y: y + 4, fill: '#666', 'font-size': '10', 'text-anchor': 'end' });
            text.textContent = Math.round(val).toLocaleString();
            svg.appendChild(text);
        }

        // Bars
        datasets.forEach((dataset, di) => {
            dataset.data.forEach((val, gi) => {
                const barH = (Math.abs(val - allMin) / valRange) * chartH;
                const x = margin.left + gi * groupWidth + (groupWidth - barWidth * barsPerGroup) / 2 + di * barWidth;
                const y = margin.top + chartH - barH;
                const rect = createEl('rect', {
                    x, y, width: barWidth - 1, height: barH, rx: barRadius, ry: barRadius,
                    fill: dataset.color || COLORS[di % COLORS.length], opacity: '0.85'
                });
                tooltip(rect, `${dataset.label}: ${val.toLocaleString()}`);
                svg.appendChild(rect);
                if (showValues && barH > 18) {
                    const txt = createEl('text', { x: x + barWidth/2 - 1, y: y - 5, fill: '#ccc', 'font-size': '9', 'text-anchor': 'middle' });
                    txt.textContent = val >= 1000 ? (val/1000).toFixed(1)+'k' : val;
                    svg.appendChild(txt);
                }
            });
        });

        // X-axis labels
        groupLabels.forEach((label, i) => {
            const x = margin.left + i * groupWidth + groupWidth / 2;
            const text = createEl('text', { x, y: height - margin.bottom + 20, fill: '#888', 'font-size': '10', 'text-anchor': 'middle' });
            text.textContent = label;
            svg.appendChild(text);
        });

        // Legend
        if (showLegend) {
            const legendY = 10;
            let legendX = margin.left;
            datasets.forEach((d, i) => {
                const color = d.color || COLORS[i % COLORS.length];
                const rect = createEl('rect', { x: legendX, y: legendY, width: 12, height: 12, rx: 2, fill: color });
                svg.appendChild(rect);
                const text = createEl('text', { x: legendX + 16, y: legendY + 10, fill: '#aaa', 'font-size': '10' });
                text.textContent = d.label;
                svg.appendChild(text);
                legendX += text.getComputedTextLength() + 30;
            });
        }

        container.appendChild(svg);
    }

    // Line Chart
    function lineChart(container, datasets, options = {}) {
        container.innerHTML = '';
        const { width = 600, height = 300, showArea = true, showDots = true, showLegend = true, curve = true } = options;
        const svg = createSVG(width, height);
        const margin = { top: 30, right: 20, bottom: 50, left: 60 };
        const chartW = width - margin.left - margin.right;
        const chartH = height - margin.top - margin.bottom;
        const labels = datasets[0]?.labels || [];
        const allValues = datasets.flatMap(d => d.data);
        const maxVal = Math.max(...allValues, 1);
        const minVal = Math.min(...allValues, 0);
        const range = maxVal - minVal || 1;
        const stepX = labels.length > 1 ? chartW / (labels.length - 1) : chartW;

        // Grid
        for (let i = 0; i <= 5; i++) {
            const y = margin.top + (chartH * i / 5);
            const line = createEl('line', { x1: margin.left, y1: y, x2: width - margin.right, y2: y, stroke: '#222', 'stroke-width': '1' });
            svg.appendChild(line);
            const val = minVal + range * (5 - i) / 5;
            const text = createEl('text', { x: margin.left - 8, y: y + 4, fill: '#666', 'font-size': '10', 'text-anchor': 'end' });
            text.textContent = Math.round(val).toLocaleString();
            svg.appendChild(text);
        }

        datasets.forEach((dataset, di) => {
            const color = dataset.color || COLORS[di % COLORS.length];
            const points = dataset.data.map((v, i) => {
                const x = margin.left + i * stepX;
                const y = margin.top + chartH - ((v - minVal) / range) * chartH;
                return `${x},${y}`;
            });

            if (showArea) {
                const areaPoints = `${margin.left},${margin.top + chartH} ` + points.join(' ') + ` ${margin.left + (labels.length-1)*stepX},${margin.top + chartH}`;
                const area = createEl('polygon', { points: areaPoints, fill: color, opacity: '0.08' });
                svg.appendChild(area);
            }

            if (curve && points.length > 2) {
                let d = `M ${points[0]}`;
                for (let i = 1; i < points.length; i++) {
                    const [px, py] = points[i-1].split(',').map(Number);
                    const [cx, cy] = points[i].split(',').map(Number);
                    const cp1x = px + stepX * 0.5;
                    const cp2x = cx - stepX * 0.5;
                    d += ` C ${cp1x},${py} ${cp2x},${cy} ${cx},${cy}`;
                }
                const path = createEl('path', { d, fill: 'none', stroke: color, 'stroke-width': '2.5', 'stroke-linecap': 'round' });
                svg.appendChild(path);
            } else {
                const line = createEl('polyline', { points: points.join(' '), fill: 'none', stroke: color, 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
                svg.appendChild(line);
            }

            if (showDots) {
                dataset.data.forEach((v, i) => {
                    const x = margin.left + i * stepX;
                    const y = margin.top + chartH - ((v - minVal) / range) * chartH;
                    const dot = createEl('circle', { cx: x, cy: y, r: '3.5', fill: color, stroke: '#0a0a12', 'stroke-width': '2' });
                    tooltip(dot, `${dataset.label}: ${v.toLocaleString()}`);
                    svg.appendChild(dot);
                });
            }
        });

        // X-axis labels
        labels.forEach((label, i) => {
            const x = margin.left + i * stepX;
            const text = createEl('text', { x, y: height - margin.bottom + 20, fill: '#888', 'font-size': '10', 'text-anchor': 'middle' });
            text.textContent = label;
            svg.appendChild(text);
        });

        // Legend
        if (showLegend) {
            const legendY = 10;
            let legendX = margin.left;
            datasets.forEach((d, i) => {
                const color = d.color || COLORS[i % COLORS.length];
                const rect = createEl('rect', { x: legendX, y: legendY, width: 12, height: 12, rx: 2, fill: color });
                svg.appendChild(rect);
                const text = createEl('text', { x: legendX + 16, y: legendY + 10, fill: '#aaa', 'font-size': '10' });
                text.textContent = d.label;
                svg.appendChild(text);
                legendX += text.getComputedTextLength() + 30;
            });
        }

        container.appendChild(svg);
    }

    // Pie/Doughnut Chart
    function pieChart(container, data, options = {}) {
        container.innerHTML = '';
        const { width = 300, height = 300, radius = 100, innerRadius = 0, showLabels = true } = options;
        const svg = createSVG(width, height);
        const cx = width / 2;
        const cy = height / 2;
        const total = data.reduce((s, d) => s + d.value, 0);
        let startAngle = -Math.PI / 2;

        data.forEach((d, i) => {
            const angle = (d.value / total) * 2 * Math.PI;
            const endAngle = startAngle + angle;
            const x1 = cx + radius * Math.cos(startAngle);
            const y1 = cy + radius * Math.sin(startAngle);
            const x2 = cx + radius * Math.cos(endAngle);
            const y2 = cy + radius * Math.sin(endAngle);
            const x1i = cx + innerRadius * Math.cos(startAngle);
            const y1i = cy + innerRadius * Math.sin(startAngle);
            const x2i = cx + innerRadius * Math.cos(endAngle);
            const y2i = cy + innerRadius * Math.sin(endAngle);
            const largeArc = angle > Math.PI ? 1 : 0;
            const color = d.color || COLORS[i % COLORS.length];
            const midAngle = startAngle + angle / 2;
            const labelR = radius + 20;
            const lx = cx + labelR * Math.cos(midAngle);
            const ly = cy + labelR * Math.sin(midAngle);

            if (innerRadius > 0) {
                const path = createEl('path', {
                    d: `M ${x1i},${y1i} L ${x1},${y1} A ${radius},${radius} 0 ${largeArc},1 ${x2},${y2} L ${x2i},${y2i} A ${innerRadius},${innerRadius} 0 ${largeArc},0 ${x1i},${y1i} Z`,
                    fill: color, opacity: '0.85'
                });
                tooltip(path, `${d.label}: ${d.value} (${((d.value/total)*100).toFixed(1)}%)`);
                svg.appendChild(path);
            } else {
                const path = createEl('path', {
                    d: `M ${cx},${cy} L ${x1},${y1} A ${radius},${radius} 0 ${largeArc},1 ${x2},${y2} Z`,
                    fill: color, opacity: '0.85'
                });
                tooltip(path, `${d.label}: ${d.value} (${((d.value/total)*100).toFixed(1)}%)`);
                svg.appendChild(path);
            }

            if (showLabels && angle > 0.2) {
                const text = createEl('text', { x: lx, y: ly + 4, fill: '#ccc', 'font-size': '10', 'text-anchor': 'middle' });
                text.textContent = d.label;
                svg.appendChild(text);
            }
            startAngle = endAngle;
        });

        if (innerRadius > 0) {
            const centerText = createEl('text', { x: cx, y: cy + 4, fill: '#fff', 'font-size': '18', 'font-weight': 'bold', 'text-anchor': 'middle' });
            centerText.textContent = total.toLocaleString();
            svg.appendChild(centerText);
        }

        container.appendChild(svg);
    }

    // Scatter Plot
    function scatterChart(container, datasets, options = {}) {
        container.innerHTML = '';
        const { width = 500, height = 300 } = options;
        const svg = createSVG(width, height);
        const margin = { top: 20, right: 20, bottom: 40, left: 50 };
        const chartW = width - margin.left - margin.right;
        const chartH = height - margin.top - margin.bottom;
        const allX = datasets.flatMap(d => d.data.map(p => p.x));
        const allY = datasets.flatMap(d => d.data.map(p => p.y));
        const xMin = Math.min(...allX, 0);
        const xMax = Math.max(...allX, 1);
        const yMin = Math.min(...allY, 0);
        const yMax = Math.max(...allY, 1);
        const xRange = xMax - xMin || 1;
        const yRange = yMax - yMin || 1;

        datasets.forEach((dataset, di) => {
            const color = dataset.color || COLORS[di % COLORS.length];
            dataset.data.forEach((point) => {
                const cx = margin.left + ((point.x - xMin) / xRange) * chartW;
                const cy = margin.top + chartH - ((point.y - yMin) / yRange) * chartH;
                const circle = createEl('circle', { cx, cy, r: point.r || 4, fill: color, opacity: '0.7' });
                tooltip(circle, `${dataset.label}: (${point.x}, ${point.y})`);
                svg.appendChild(circle);
            });
        });

        container.appendChild(svg);
    }

    // Dependency Graph (for plugins)
    function dependencyGraph(container, nodes, edges, options = {}) {
        container.innerHTML = '';
        const { width = 600, height = 400 } = options;
        const svg = createSVG(width, height);
        const nodeR = 20;
        const nodeMap = {};

        // Simple force-directed layout approximation
        const centerX = width / 2;
        const centerY = height / 2;
        const angleStep = (2 * Math.PI) / nodes.length;
        nodes.forEach((node, i) => {
            nodeMap[node.id] = {
                ...node,
                x: centerX + Math.cos(i * angleStep) * (width * 0.3),
                y: centerY + Math.sin(i * angleStep) * (height * 0.3)
            };
        });

        // Draw edges
        edges.forEach(edge => {
            const from = nodeMap[edge.from];
            const to = nodeMap[edge.to];
            if (!from || !to) return;
            const line = createEl('line', {
                x1: from.x, y1: from.y, x2: to.x, y2: to.y,
                stroke: '#444', 'stroke-width': '1.5', 'stroke-dasharray': '4,4'
            });
            svg.appendChild(line);
        });

        // Draw nodes
        nodes.forEach(node => {
            const n = nodeMap[node.id];
            const circle = createEl('circle', {
                cx: n.x, cy: n.y, r: nodeR,
                fill: node.type === 'core' ? '#8B5CF6' : node.type === 'plugin' ? '#10B981' : '#F59E0B',
                stroke: '#0a0a12', 'stroke-width': '2'
            });
            tooltip(circle, `${node.label} (${node.version || '1.0.0'})`);
            svg.appendChild(circle);
            const text = createEl('text', {
                x: n.x, y: n.y + 4, fill: '#fff', 'font-size': '8', 'text-anchor': 'middle', 'font-weight': 'bold'
            });
            text.textContent = node.label.substring(0, 6);
            svg.appendChild(text);
        });

        container.appendChild(svg);
    }

    return { sparkline, barChart, lineChart, pieChart, scatterChart, dependencyGraph, COLORS };
})();
