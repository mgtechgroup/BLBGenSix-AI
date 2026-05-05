// admin.js - Complete Admin Dashboard Interactivity
// ES5-compatible, no external dependencies

// ==================== Toast System ====================
function showToast(message, type) {
    type = type || "info";
    var container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        container.style.cssText = "position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;";
        document.body.appendChild(container);
    }
    var colors = { success: "#10B981", error: "#EF4444", warning: "#F59E0B", info: "#3B82F6" };
    var icons = { success: "OK", error: "X", warning: "!", info: "i" };
    var toast = document.createElement("div");
    toast.style.cssText = "background:" + colors[type] + ";color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;min-width:250px;box-shadow:0 4px 12px rgba(0,0,0,0.3);display:flex;align-items:center;gap:8px;animation:slideIn 0.3s ease;";
    toast.innerHTML = '<span style="font-weight:bold;">' + icons[type] + '</span> ' + message;
    container.appendChild(toast);
    setTimeout(function() { toast.style.opacity = "0"; toast.style.transition = "opacity 0.3s"; setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300); }, 3000);
}

// ==================== Mock Data ====================
var MockData = {
    users: (function() {
        var arr = [];
        var names = ["Alice Johnson", "Bob Smith", "Charlie Brown", "Diana Prince", "Eve Adams", "Frank Castle", "Grace Lee", "Hank Pym", "Iris West", "Jack Ryan", "Karen Page", "Leo Fitz", "Mia Torres", "Nate Grey", "Olivia Pope", "Paul Atreides", "Quinn Fabray", "Rachel Green", "Steve Rogers", "Tony Stark", "Uma Thurman", "Victor Von", "Wanda Maximoff", "Xavier Charles", "Yara Flor", "Zach Snyder"];
        var roles = ["admin", "editor", "viewer", "api"];
        var statuses = ["active", "inactive", "suspended"];
        for (var i = 0; i < 87; i++) {
            arr.push({
                id: "USR-" + String(1000 + i),
                name: names[i % names.length] + " " + (i > 25 ? String(i) : ""),
                email: (names[i % names.length]).toLowerCase() + (i > 25 ? String(i) : "") + "@example.com",
                role: roles[i % roles.length],
                status: statuses[i % 3 === 0 ? 0 : (i % 3 === 1 ? 1 : 2)],
                joined: new Date(2024, (i * 7) % 12, (i * 13) % 28 + 1).toISOString().slice(0, 10),
                lastActive: new Date(2026, 3, (i * 3) % 28 + 1).toISOString().slice(0, 10),
                requests: Math.floor(Math.random() * 50000) + 100,
                revenue: Math.floor(Math.random() * 10000) + 50
            });
        }
        return arr;
    })(),
    
    scrapers: (function() {
        var arr = [];
        var scraperNames = ["Alpha Crawler", "Beta Scraper", "Gamma Bot", "Delta Fetcher", "Epsilon Spider", "Foxtrot Parser", "Gamma Ranger", "Hotel Miner", "India Extractor", "Juliet Probe", "Kilo Snatcher", "Lima Harvester", "Mike Digger", "November Collector", "Oscar Gatherer", "Papa Spider", "Quebec Crawler", "Romeo Bot", "Sierra Fetcher", "Tango Parser", "Uniform Miner", "Victor Extractor", "Whiskey Probe", "Xray Harvester", "Yankee Collector", "Zulu Gatherer"];
        var types = ["web", "api", "rss", "pdf", "image"];
        var statuses = ["online", "offline", "error"];
        for (var i = 0; i < 802; i++) {
            arr.push({
                id: "SCR-" + String(10000 + i),
                name: scraperNames[i % scraperNames.length] + " " + (i > 25 ? String(Math.floor(i / 26)) : ""),
                type: types[i % types.length],
                status: statuses[i % 7 === 0 ? 2 : (i % 3 === 0 ? 1 : 0)],
                success: Math.floor(70 + Math.random() * 30),
                requests: Math.floor(Math.random() * 100000) + 1000,
                enabled: i % 3 !== 1
            });
        }
        return arr;
    })(),
    
    revenueStreams: [
        { name: "API Subscriptions", monthly: 42000, yearly: 504000, color: "#3B82F6" },
        { name: "Data Exports", monthly: 28000, yearly: 336000, color: "#10B981" },
        { name: "Premium Scrapers", monthly: 35000, yearly: 420000, color: "#8B5CF6" },
        { name: "Enterprise Plans", monthly: 18000, yearly: 216000, color: "#F59E0B" },
        { name: "Add-on Services", monthly: 12000, yearly: 144000, color: "#EF4444" },
        { name: "Marketplace", monthly: 8500, yearly: 102000, color: "#06B6D4" },
        { name: "Consulting", monthly: 15000, yearly: 180000, color: "#EC4899" },
        { name: "Training", monthly: 6000, yearly: 72000, color: "#14B8A6" },
        { name: "Support Plans", monthly: 9500, yearly: 114000, color: "#F97316" },
        { name: "Other", monthly: 4500, yearly: 54000, color: "#6366F1" }
    ],
    
    plugins: [
        { id: "PLG-001", name: "Web Scraper Pro", version: "2.1.0", author: "ScraperCorp", status: "active", rating: 4.8, installs: 1245, description: "Advanced web scraping with JS rendering" },
        { id: "PLG-002", name: "API Connector", version: "1.5.3", author: "APIMasters", status: "active", rating: 4.5, installs: 892, description: "Connect to REST and GraphQL APIs" },
        { id: "PLG-003", name: "Data Transformer", version: "3.0.1", author: "DataFlow Inc", status: "inactive", rating: 4.2, installs: 567, description: "Transform data between formats" },
        { id: "PLG-004", name: "PDF Extractor", version: "1.2.0", author: "DocTech", status: "active", rating: 4.9, installs: 2103, description: "Extract text and tables from PDFs" },
        { id: "PLG-005", name: "Image Analyzer", version: "2.0.5", author: "VisionAI", status: "active", rating: 4.3, installs: 756, description: "OCR and image content analysis" },
        { id: "PLG-006", name: "Scheduler Pro", version: "1.8.2", author: "TimeTech", status: "update", rating: 4.6, installs: 445, description: "Advanced scheduling with cron support" },
        { id: "PLG-007", name: "Proxy Manager", version: "2.3.1", author: "NetTools", status: "active", rating: 4.1, installs: 1890, description: "Manage proxy rotation and pools" },
        { id: "PLG-008", name: "Rate Limiter", version: "1.0.0", author: "GuardTech", status: "active", rating: 4.7, installs: 3102, description: "Intelligent rate limiting and throttling" },
        { id: "PLG-009", name: "Data Validator", version: "1.5.0", author: "QualityFirst", status: "inactive", rating: 3.9, installs: 234, description: "Validate scraped data quality" },
        { id: "PLG-010", name: "Export Wizard", version: "3.1.0", author: "ExportPro", status: "active", rating: 4.4, installs: 1567, description: "Export to CSV, JSON, XML, Excel" },
        { id: "PLG-011", name: "Notification Hub", version: "2.0.0", author: "AlertSys", status: "active", rating: 4.0, installs: 678, description: "Multi-channel notifications" },
        { id: "PLG-012", name: "Cache Manager", version: "1.3.5", author: "SpeedTech", status: "update", rating: 4.8, installs: 4234, description: "Intelligent caching layer" },
        { id: "PLG-013", name: "Auth Provider", version: "2.1.0", author: "SecureAuth", status: "active", rating: 4.5, installs: 987, description: "OAuth, SAML, and API key auth" },
        { id: "PLG-014", name: "Log Analyzer", version: "1.7.0", author: "LogTech", status: "active", rating: 4.2, installs: 345, description: "Analyze and visualize logs" },
        { id: "PLG-015", name: "Backup Manager", version: "3.0.0", author: "SafeData", status: "inactive", rating: 4.6, installs: 1200, description: "Automated backup and restore" },
        { id: "PLG-016", name: "Template Engine", version: "1.2.0", author: "Templaters", status: "active", rating: 3.8, installs: 156, description: "Custom output templates" },
        { id: "PLG-017", name: "ML Classifier", version: "2.0.0", author: "AILabs", status: "active", rating: 4.9, installs: 567, description: "ML-based content classification" },
        { id: "PLG-018", name: "Dashboard Widgets", version: "1.5.0", author: "WidgetCo", status: "active", rating: 4.3, installs: 2340, description: "Custom dashboard widgets" }
    ],
    
    features: [
        { id: "FT-001", name: "Advanced Search", enabled: true, rollout: 100, group: "Core", description: "Full-text search across all data" },
        { id: "FT-002", name: "Real-time Sync", enabled: true, rollout: 85, group: "Core", description: "Real-time data synchronization" },
        { id: "FT-003", name: "API v2", enabled: false, rollout: 0, group: "API", description: "New REST API version" },
        { id: "FT-004", name: "Webhooks", enabled: true, rollout: 60, group: "API", description: "Outgoing webhook support" },
        { id: "FT-005", name: "Multi-tenant", enabled: true, rollout: 100, group: "Enterprise", description: "Multi-tenant architecture" },
        { id: "FT-006", name: "SSO Integration", enabled: false, rollout: 0, group: "Enterprise", description: "SAML/OAuth SSO support" },
        { id: "FT-007", name: "Data Export API", enabled: true, rollout: 45, group: "API", description: "Programmatic data export" },
        { id: "FT-008", name: "AI Summarizer", enabled: true, rollout: 25, group: "AI", description: "AI-powered content summarization" },
        { id: "FT-009", name: "Smart Scheduling", enabled: false, rollout: 0, group: "AI", description: "AI-optimized scraping schedules" },
        { id: "FT-010", name: "Anomaly Detection", enabled: true, rollout: 15, group: "AI", description: "Detect data anomalies" },
        { id: "FT-011", name: "Custom Fields", enabled: true, rollout: 100, group: "Core", description: "User-defined custom fields" },
        { id: "FT-012", name: "Audit Log", enabled: true, rollout: 100, group: "Security", description: "Comprehensive audit logging" },
        { id: "FT-013", name: "IP Whitelist", enabled: false, rollout: 0, group: "Security", description: "IP-based access control" },
        { id: "FT-014", name: "Rate Limiting", enabled: true, rollout: 100, group: "Security", description: "API rate limiting" },
        { id: "FT-015", name: "GraphQL Endpoint", enabled: false, rollout: 0, group: "API", description: "GraphQL API endpoint" }
    ],
    
    auditLog: [
        { time: "2026-04-25 14:32:15", user: "Alice Johnson", action: "User Login", target: "System", ip: "192.168.1.105" },
        { time: "2026-04-25 14:28:03", user: "Bob Smith", action: "Modified Feature Flag", target: "AI Summarizer", ip: "10.0.0.42" },
        { time: "2026-04-25 14:15:22", user: "System", action: "Backup Completed", target: "Database", ip: "127.0.0.1" },
        { time: "2026-04-25 13:45:10", user: "Diana Prince", action: "Installed Plugin", target: "PDF Extractor v2.1.0", ip: "172.16.0.23" },
        { time: "2026-04-25 13:20:55", user: "Charlie Brown", action: "API Key Generated", target: "Production API", ip: "192.168.1.89" },
        { time: "2026-04-25 12:55:30", user: "Eve Adams", action: "Scraper Enabled", target: "Alpha Crawler", ip: "10.0.0.15" },
        { time: "2026-04-25 12:30:18", user: "Frank Castle", action: "Settings Updated", target: "Rate Limits", ip: "192.168.1.201" },
        { time: "2026-04-25 11:45:42", user: "Grace Lee", action: "User Suspended", target: "USR-1087", ip: "172.16.0.55" },
        { time: "2026-04-25 11:20:05", user: "Hank Pym", action: "Plugin Disabled", target: "Data Validator", ip: "10.0.0.78" },
        { time: "2026-04-25 10:55:33", user: "Iris West", action: "Export Completed", target: "Revenue Report Q1", ip: "192.168.1.150" }
    ]
};


// admin_part2.js - Dashboard and Navigation functions
// ==================== Navigation ====================
function navigateToSection(section) {
    var sections = ["dashboard", "users", "revenue", "scrapers", "plugins", "features", "settings"];
    sections.forEach(function(s) {
        var el = document.getElementById("section-" + s);
        if (el) el.style.display = s === section ? "block" : "none";
        var btn = document.getElementById("nav-" + s);
        if (btn) btn.classList.remove("active");
    });
    var activeBtn = document.getElementById("nav-" + section);
    if (activeBtn) activeBtn.classList.add("active");
    window.location.hash = section;
    if (section === "dashboard") loadDashboard();
    else if (section === "users") loadUsers();
    else if (section === "revenue") loadRevenue();
    else if (section === "scrapers") loadScrapers();
    else if (section === "plugins") loadPlugins();
    else if (section === "features") loadFeatures();
    else if (section === "settings") loadSettings();
}

// ==================== Dashboard Section ====================
function loadDashboard() {
    renderDashboardStats();
    renderDashboardCharts();
    renderActivityFeed();
    renderSystemHealth();
}

function renderDashboardStats() {
    var stats = [
        { label: "Total Users", value: MockData.users.length, change: "+12%", color: "#3B82F6" },
        { label: "Active Scrapers", value: MockData.scrapers.filter(function(s) { return s.status === "online"; }).length, change: "+5", color: "#10B981" },
        { label: "Revenue (MTD)", value: "$" + MockData.revenueStreams.reduce(function(sum, s) { return sum + s.monthly; }, 0).toLocaleString(), change: "+18%", color: "#8B5CF6" },
        { label: "API Requests", value: MockData.users.reduce(function(sum, u) { return sum + u.requests; }, 0).toLocaleString(), change: "+23%", color: "#F59E0B" }
    ];
    var container = document.getElementById("dashboard-stats");
    if (!container) return;
    container.innerHTML = stats.map(function(s) {
        return '<div class="stat-card"><div class="stat-label">' + s.label + '</div><div class="stat-value" style="color:' + s.color + '">' + s.value + '</div><div class="stat-change positive">' + s.change + ' vs last month</div></div>';
    }).join("");
}

function renderDashboardCharts() {
    var container1 = document.getElementById("dash-chart-1");
    var container2 = document.getElementById("dash-chart-2");
    var container3 = document.getElementById("dash-chart-3");
    if (container1) {
        var data = [];
        for (var i = 0; i < 30; i++) { data.push(Math.floor(800 + Math.random() * 400)); }
        Charts.lineChart(container1, [{ label: "API Requests", data: data, color: "#3B82F6" }], { width: 350, height: 150 });
    }
    if (container2) {
        var revData = MockData.revenueStreams.slice(0, 6).map(function(s) { return { label: s.name, value: s.monthly, color: s.color }; });
        Charts.pieChart(container2, revData, { width: 350, height: 150 });
    }
    if (container3) {
        var topScrapers = MockData.scrapers.filter(function(s) { return s.status === "online"; }).slice(0, 5);
        var barData = [{ label: "Requests", data: topScrapers.map(function(s) { return s.requests; }), color: "#10B981" }];
        Charts.barChart(container3, barData, { width: 350, height: 150 });
    }
}

function renderActivityFeed() {
    var container = document.getElementById("activity-feed");
    if (!container) return;
    container.innerHTML = MockData.auditLog.map(function(log) {
        return '<div class="log-entry"><span class="log-time">' + log.time + '</span> <strong>' + log.user + '</strong> ' + log.action + ': ' + log.target + ' <span style="color:#666;font-size:11px;">(' + log.ip + ')</span></div>';
    }).join("");
}

function renderSystemHealth() {
    var container = document.getElementById("system-health");
    if (!container) return;
    var services = [
        { name: "API Gateway", status: "healthy", uptime: "99.97%" },
        { name: "Database", status: "healthy", uptime: "99.95%" },
        { name: "Scraper Engine", status: "warning", uptime: "98.20%" },
        { name: "Cache Layer", status: "healthy", uptime: "99.89%" },
        { name: "Auth Service", status: "healthy", uptime: "99.99%" }
    ];
    container.innerHTML = services.map(function(s) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #1a1a2e;"><span><span class="status-dot ' + s.status + '"></span> ' + s.name + '</span><span style="color:#666;font-size:12px;">' + s.uptime + '</span></div>';
    }).join("");
}

// ==================== User Management Section ====================
var userState = { data: [], filtered: [], page: 1, perPage: 20, search: "", role: "all", status: "all", selected: [] };

function loadUsers() {
    userState.data = JSON.parse(JSON.stringify(MockData.users));
    userState.filtered = JSON.parse(JSON.stringify(MockData.users));
    userState.page = 1;
    userState.selected = [];
    renderUserTable();
    renderUserStats();
}

function renderUserTable() {
    var data = JSON.parse(JSON.stringify(userState.data));
    if (userState.role !== "all") { data = data.filter(function(u) { return u.role === userState.role; }); }
    if (userState.status !== "all") { data = data.filter(function(u) { return u.status === userState.status; }); }
    if (userState.search) {
        var q = userState.search.toLowerCase();
        data = data.filter(function(u) { return u.name.toLowerCase().indexOf(q) >= 0 || u.email.toLowerCase().indexOf(q) >= 0 || u.id.toLowerCase().indexOf(q) >= 0; });
    }
    userState.filtered = data;
    var totalPages = Math.ceil(data.length / userState.perPage);
    var start = (userState.page - 1) * userState.perPage;
    var pageData = data.slice(start, start + userState.perPage);
    var tbody = document.getElementById("user-tbody");
    if (!tbody) return;
    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#666;">No users found</td></tr>';
    } else {
        tbody.innerHTML = pageData.map(function(u) {
            var checked = userState.selected.indexOf(u.id) >= 0 ? "checked" : "";
            return '<tr><td><input type="checkbox" ' + checked + ' onchange="toggleUserSelect(\'' + u.id + '\', this.checked)"></td><td style="font-weight:600;">' + u.name + '</td><td>' + u.email + '</td><td><span class="badge badge-' + u.role + '">' + u.role + '</span></td><td><span class="badge badge-' + u.status + '">' + u.status + '</span></td><td>' + u.joined + '</td><td>' + u.requests.toLocaleString() + '</td><td><button class="btn btn-sm btn-primary" onclick="viewUser(\'' + u.id + '\')">View</button> <button class="btn btn-sm btn-secondary" onclick="editUser(\'' + u.id + '\')">Edit</button></td></tr>';
        }).join("");
    }
    var pag = document.getElementById("user-pagination");
    if (pag) {
        var html = '<button ' + (userState.page <= 1 ? "disabled" : "") + ' onclick="userState.page--;renderUserTable();">Prev</button>';
        html += '<span style="color:#666;font-size:12px;padding:0 12px;">Page ' + userState.page + ' of ' + totalPages + '</span>';
        html += '<button ' + (userState.page >= totalPages ? "disabled" : "") + ' onclick="userState.page++;renderUserTable();">Next</button>';
        pag.innerHTML = html;
    }
    var info = document.getElementById("user-info");
    if (info) { info.innerHTML = 'Showing ' + (start + 1) + '-' + (start + pageData.length) + ' of ' + data.length + ' users'; }
}

function renderUserStats() {
    var container = document.getElementById("user-stats");
    if (!container) return;
    var roles = {};
    MockData.users.forEach(function(u) { roles[u.role] = (roles[u.role] || 0) + 1; });
    container.innerHTML = '<div style="display:flex;gap:16px;flex-wrap:wrap;">' + Object.keys(roles).map(function(r) {
        return '<div style="background:#111;padding:12px 16px;border-radius:8px;"><div style="font-size:12px;color:#666;">' + r + '</div><div style="font-size:20px;font-weight:700;color:#3B82F6;">' + roles[r] + '</div></div>';
    }).join("") + '</div>';
}

function searchUsers(q) { userState.search = q; userState.page = 1; renderUserTable(); }
function filterUsersByRole(r) { userState.role = r; userState.page = 1; renderUserTable(); }
function filterUsersByStatus(s) { userState.status = s; userState.page = 1; renderUserTable(); }
function toggleUserSelect(id, checked) {
    var idx = userState.selected.indexOf(id);
    if (checked && idx < 0) userState.selected.push(id);
    else if (!checked && idx >= 0) userState.selected.splice(idx, 1);
}
function bulkUserAction(action) {
    if (userState.selected.length === 0) { showToast("No users selected", "warning"); return; }
    showToast(action + " applied to " + userState.selected.length + " users", "success");
}
function viewUser(id) { showToast("View user: " + id, "info"); }
function editUser(id) { showToast("Edit user: " + id, "info"); }


// admin_part3.js - Revenue and Scrapers sections
// ==================== Revenue Section ====================
var revenuePeriod = "monthly";

function loadRevenue() {
    renderRevenueCards();
    renderRevenueChart();
    renderRevenueTable();
}

function setRevenuePeriod(p) {
    revenuePeriod = p;
    var buttons = document.querySelectorAll("#section-revenue .chart-toggle button");
    for (var i = 0; i < buttons.length; i++) { buttons[i].classList.remove("active"); }
    var btn = document.getElementById("rev-toggle-" + p);
    if (btn) btn.classList.add("active");
    renderRevenueCards();
    renderRevenueChart();
}

function renderRevenueCards() {
    var container = document.getElementById("revenue-cards");
    if (!container) return;
    container.innerHTML = MockData.revenueStreams.map(function(s) {
        var val = revenuePeriod === "monthly" ? s.monthly : s.yearly;
        return '<div class="revenue-card"><div class="revenue-label">' + s.name + '</div><div class="revenue-amount" style="color:' + s.color + '">$' + val.toLocaleString() + '</div><div style="margin-top:8px;height:30px;" id="rev-spark-' + s.name.replace(/\s/g, "-") + '"></div></div>';
    }).join("");
    MockData.revenueStreams.forEach(function(s) {
        var el = document.getElementById("rev-spark-" + s.name.replace(/\s/g, "-"));
        if (el) Charts.sparkline(el, [s.monthly*0.7, s.monthly*0.8, s.monthly*0.9, s.monthly, s.monthly*1.05], s.color, 80, 30);
    });
}

function renderRevenueChart() {
    var container = document.getElementById("revenue-chart");
    if (!container) return;
    var datasets = MockData.revenueStreams.map(function(s) {
        var base = revenuePeriod === "monthly" ? s.monthly : s.yearly / 12;
        var data = [];
        for (var i = 0; i < 12; i++) { data.push(Math.floor(base * (0.8 + Math.random() * 0.4))); }
        return { label: s.name, data: data, color: s.color };
    });
    Charts.barChart(container, datasets.slice(0, 5), { width: 700, height: 300 });
}

function renderRevenueTable() {
    var tbody = document.getElementById("revenue-tbody");
    if (!tbody) return;
    tbody.innerHTML = MockData.revenueStreams.map(function(s) {
        return '<tr><td style="font-weight:600;">' + s.name + '</td><td style="color:' + s.color + ';font-weight:600;">$' + s.monthly.toLocaleString() + '</td><td style="color:' + s.color + ';font-weight:600;">$' + s.yearly.toLocaleString() + '</td><td>$' + (s.yearly * 0.3).toLocaleString() + '</td><td><div style="width:60px;height:6px;background:#222;border-radius:3px;"><div style="width:' + (60*(s.monthly/45000)) + 'px;height:100%;background:' + s.color + ';border-radius:3px;"></div></div></td></tr>';
    }).join("");
}

// ==================== Scrapers Section ====================
var scraperState = { data: [], filtered: [], page: 1, perPage: 20, search: "", filter: "all", selected: [] };

function loadScrapers() {
    scraperState.data = JSON.parse(JSON.stringify(MockData.scrapers));
    scraperState.filtered = JSON.parse(JSON.stringify(MockData.scrapers));
    scraperState.page = 1;
    scraperState.selected = [];
    renderScraperGrid();
    renderScraperLog();
}

function renderScraperGrid() {
    var data = JSON.parse(JSON.stringify(scraperState.data));
    if (scraperState.filter !== "all") { data = data.filter(function(s) { return s.status === scraperState.filter; }); }
    if (scraperState.search) {
        var q = scraperState.search.toLowerCase();
        data = data.filter(function(s) { return s.name.toLowerCase().indexOf(q) >= 0 || s.id.toLowerCase().indexOf(q) >= 0; });
    }
    scraperState.filtered = data;
    var totalPages = Math.ceil(data.length / scraperState.perPage);
    var start = (scraperState.page - 1) * scraperState.perPage;
    var pageData = data.slice(start, start + scraperState.perPage);
    var container = document.getElementById("scraper-grid");
    if (!container) return;
    if (pageData.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">S</div><div class="empty-state-text">No scrapers found</div></div>';
    } else {
        container.innerHTML = pageData.map(function(s) {
            return '<div class="scraper-card ' + s.status + '" data-id="' + s.id + '"><div class="scraper-header"><div class="scraper-name">' + s.name + '</div><span class="scraper-status status-' + s.status + '">' + s.status + '</span></div><div class="scraper-meta"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Type: ' + s.type + '</span><span>Success: ' + s.success + '%</span></div><div style="display:flex;justify-content:space-between;"><span>Requests: ' + s.requests.toLocaleString() + '</span><label class="toggle" style="transform:scale(0.8);"><input type="checkbox" ' + (s.enabled ? "checked" : "") + ' onchange="toggleScraper(\'' + s.id + '\', this.checked)"><span class="toggle-slider"></span></label></div></div></div>';
        }).join("");
    }
    var pag = document.getElementById("scraper-pagination");
    if (pag) {
        var html = '<button ' + (scraperState.page <= 1 ? "disabled" : "") + ' onclick="scraperState.page--;renderScraperGrid();">Prev</button>';
        html += '<span style="color:#666;font-size:12px;padding:0 12px;">Page ' + scraperState.page + ' of ' + totalPages + '</span>';
        html += '<button ' + (scraperState.page >= totalPages ? "disabled" : "") + ' onclick="scraperState.page++;renderScraperGrid();">Next</button>';
        pag.innerHTML = html;
    }
    var stats = document.getElementById("scraper-stats");
    if (stats) {
        var online = scraperState.data.filter(function(s) { return s.status === "online"; }).length;
        var offline = scraperState.data.filter(function(s) { return s.status === "offline"; }).length;
        var error = scraperState.data.filter(function(s) { return s.status === "error"; }).length;
        stats.innerHTML = '<div style="display:flex;gap:16px;font-size:13px;"><span style="color:#10B981;">Online: ' + online + '</span><span style="color:#EF4444;">Offline: ' + offline + '</span><span style="color:#F59E0B;">Error: ' + error + '</span><span style="color:#666;">Total: ' + scraperState.data.length + '</span></div>';
    }
}

function searchScrapers(q) { scraperState.search = q; scraperState.page = 1; renderScraperGrid(); }
function filterScrapers(f) { scraperState.filter = f; scraperState.page = 1; renderScraperGrid(); }

function toggleScraper(id, enabled) {
    var s = MockData.scrapers.find(function(x) { return x.id === id; });
    if (s) { s.enabled = enabled; showToast("Scraper " + s.name + " " + (enabled ? "enabled" : "disabled"), enabled ? "success" : "warning"); }
    renderScraperGrid();
}

function bulkEnableScrapers() {
    scraperState.filtered.forEach(function(s) { s.enabled = true; });
    showToast("All visible scrapers enabled", "success");
    renderScraperGrid();
}
function bulkDisableScrapers() {
    scraperState.filtered.forEach(function(s) { s.enabled = false; });
    showToast("All visible scrapers disabled", "warning");
    renderScraperGrid();
}

function renderScraperLog() {
    var log = document.getElementById("scraper-log");
    if (!log) return;
    var msgs = [" started successfully", " - rate limit hit, retrying in 60s", ": Connection timeout", " completed: 1,234 items scraped", ": Authentication failed"];
    var scrapers = MockData.scrapers.slice(0, 5).map(function(s) { return s.name; });
    var entries = [];
    for (var i = 0; i < 20; i++) {
        var level = ["info","warn","error","success"][i % 4];
        var time = new Date(2026, 3, 25, i % 24, (i * 3) % 60).toLocaleTimeString();
        entries.push('<div class="log-entry"><span class="log-time">' + time + '</span> <span class="log-level-' + level + '">[' + level.toUpperCase() + ']</span> ' + scrapers[i % 5] + msgs[i % 5] + '</div>');
    }
    log.innerHTML = entries.join("");
}


// admin_part4.js - Plugins, Features, Settings sections
// ==================== Plugins Section ====================
var pluginState = { data: [], filtered: [], search: "", filter: "all" };

function loadPlugins() {
    pluginState.data = JSON.parse(JSON.stringify(MockData.plugins));
    pluginState.filtered = JSON.parse(JSON.stringify(MockData.plugins));
    renderPluginGrid();
}

function renderPluginGrid() {
    var data = JSON.parse(JSON.stringify(pluginState.data));
    if (pluginState.filter !== "all") { data = data.filter(function(p) { return p.status === pluginState.filter; }); }
    if (pluginState.search) {
        var q = pluginState.search.toLowerCase();
        data = data.filter(function(p) { return p.name.toLowerCase().indexOf(q) >= 0 || p.description.toLowerCase().indexOf(q) >= 0; });
    }
    pluginState.filtered = data;
    var container = document.getElementById("plugin-grid");
    if (!container) return;
    if (data.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">P</div><div class="empty-state-text">No plugins found</div></div>';
    } else {
        container.innerHTML = data.map(function(p) {
            var statusClass = p.status === "active" ? "success" : (p.status === "update" ? "warning" : "error");
            var stars = "";
            for (var i = 0; i < 5; i++) { stars += i < Math.floor(p.rating) ? "★" : "☆"; }
            return '<div class="plugin-card"><div class="plugin-header"><div><div class="plugin-name">' + p.name + '</div><div style="font-size:11px;color:#666;">v' + p.version + ' by ' + p.author + '</div></div><span class="badge badge-' + p.status + '">' + p.status + '</span></div><div class="plugin-desc">' + p.description + '</div><div style="display:flex;justify-content:space-between;align-items:center;margin:8px 0;"><span style="color:#F59E0B;font-size:12px;">' + stars + ' ' + p.rating + '</span><span style="color:#666;font-size:12px;">' + p.installs.toLocaleString() + ' installs</span></div><div style="display:flex;gap:8px;"><button class="btn btn-sm btn-primary" onclick="showToast(\'Install ' + p.name + '\', \'success\')">Install</button><button class="btn btn-sm btn-secondary" onclick="showToast(\'Configure ' + p.name + '\', \'info\')">Configure</button></div></div>';
        }).join("");
    }
    // Plugin chart
    var chartContainer = document.getElementById("plugin-chart");
    if (chartContainer) {
        var topPlugins = data.slice(0, 6).map(function(p) { return { label: p.name.split(" ")[0], value: p.installs, color: "#8B5CF6" }; });
        Charts.barChart(chartContainer, [{ label: "Installs", data: topPlugins.map(function(x) { return x.value; }), color: "#8B5CF6" }], { width: 400, height: 200 });
    }
}

function searchPlugins(q) { pluginState.search = q; renderPluginGrid(); }
function filterPlugins(f) { pluginState.filter = f; renderPluginGrid(); }

// ==================== Feature Flags Section ====================
function loadFeatures() {
    renderFeatureTable();
}

function renderFeatureTable() {
    var tbody = document.getElementById("feature-tbody");
    if (!tbody) return;
    tbody.innerHTML = MockData.features.map(function(f) {
        return '<tr><td style="font-weight:600;">' + f.name + '</td><td><span class="badge badge-' + (f.enabled ? "active" : "inactive") + '">' + (f.enabled ? "ON" : "OFF") + '</span></td><td><div style="display:flex;align-items:center;gap:8px;"><input type="range" min="0" max="100" value="' + f.rollout + '" style="flex:1;" onchange="updateRollout(\'' + f.id + '\', this.value)"><span style="font-size:12px;color:#666;min-width:40px;">' + f.rollout + '%</span></div></td><td>' + f.group + '</td><td><button class="btn btn-sm ' + (f.enabled ? "btn-secondary" : "btn-primary") + '" onclick="toggleFeature(\'' + f.id + '\')">' + (f.enabled ? "Disable" : "Enable") + '</button></td></tr>';
    }).join("");
    // Feature chart
    var chartContainer = document.getElementById("feature-chart");
    if (chartContainer) {
        var enabled = MockData.features.filter(function(f) { return f.enabled; }).length;
        var disabled = MockData.features.length - enabled;
        Charts.pieChart(chartContainer, [{ label: "Enabled", value: enabled, color: "#10B981" }, { label: "Disabled", value: disabled, color: "#EF4444" }], { width: 250, height: 200 });
    }
}

function toggleFeature(id) {
    var f = MockData.features.find(function(x) { return x.id === id; });
    if (f) { f.enabled = !f.enabled; showToast("Feature " + f.name + " " + (f.enabled ? "enabled" : "disabled"), f.enabled ? "success" : "warning"); renderFeatureTable(); }
}

function updateRollout(id, val) {
    var f = MockData.features.find(function(x) { return x.id === id; });
    if (f) { f.rollout = parseInt(val); renderFeatureTable(); }
}

// ==================== Settings Section ====================
function loadSettings() {
    renderAuditLog();
    renderSettingsForm();
}

function renderAuditLog() {
    var tbody = document.getElementById("audit-tbody");
    if (!tbody) return;
    tbody.innerHTML = MockData.auditLog.map(function(log) {
        return '<tr><td style="font-size:12px;color:#666;">' + log.time + '</td><td>' + log.user + '</td><td>' + log.action + '</td><td>' + log.target + '</td><td style="font-size:12px;color:#666;">' + log.ip + '</td></tr>';
    }).join("");
}

function renderSettingsForm() {
    // Settings are static in HTML, just add event listeners
    var inputs = document.querySelectorAll('#section-settings input, #section-settings select');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function() { showToast("Setting updated", "success"); });
    }
}

function saveSettings() {
    showToast("Settings saved successfully", "success");
}

// ==================== Initialization ====================
function init() {
    // Set up hash navigation
    var hash = window.location.hash.replace("#", "") || "dashboard";
    navigateToSection(hash);
    
    // Listen for hash changes
    window.addEventListener("hashchange", function() {
        var h = window.location.hash.replace("#", "") || "dashboard";
        navigateToSection(h);
    });
    
    // Show welcome toast
    setTimeout(function() { showToast("Admin Dashboard loaded successfully", "success"); }, 500);
}

// Run on DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
