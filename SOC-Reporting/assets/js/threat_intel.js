/**
 * SOC Reporting System - Threat Intelligence Feed
 * Fetches cybersecurity RSS feeds and displays them with filtering.
 */
(function () {
    'use strict';

    const RSS_PROXY = 'index.php?page=api_rss_proxy';

    const FEEDS = [
        // MITRE
        { url: 'https://medium.com/feed/mitre-attack', category: 'mitre', label: 'MITRE ATT&CK', color: '#ff3366' },
        // Government / Advisory
        { url: 'https://www.cisa.gov/cybersecurity-advisories/all.xml', category: 'cisa', label: 'CISA Alerts', color: '#ffaa00' },
        { url: 'https://www.ncsc.gov.uk/api/1/services/v1/report-rss-feed.xml', category: 'cisa', label: 'NCSC UK', color: '#ffaa00' },
        { url: 'https://cert.europa.eu/publications/security-advisories/rss', category: 'cisa', label: 'CERT-EU', color: '#ffaa00' },
        // Cyber News
        { url: 'https://feeds.feedburner.com/TheHackersNews', category: 'news', label: 'The Hacker News', color: '#00d4ff' },
        { url: 'https://www.bleepingcomputer.com/feed/', category: 'news', label: 'BleepingComputer', color: '#00d4ff' },
        { url: 'https://www.darkreading.com/rss.xml', category: 'news', label: 'Dark Reading', color: '#00d4ff' },
        { url: 'https://www.securityweek.com/feed/', category: 'news', label: 'SecurityWeek', color: '#00d4ff' },
        { url: 'https://krebsonsecurity.com/feed/', category: 'news', label: 'Krebs on Security', color: '#00d4ff' },
        { url: 'https://threatpost.com/feed/', category: 'news', label: 'Threatpost', color: '#00d4ff' },
        { url: 'https://www.schneier.com/feed/atom/', category: 'news', label: 'Schneier on Security', color: '#00d4ff' },
        { url: 'https://www.recordedfuture.com/feed', category: 'news', label: 'Recorded Future', color: '#00d4ff' },
        { url: 'https://blog.talosintelligence.com/rss/', category: 'news', label: 'Cisco Talos', color: '#00d4ff' },
        { url: 'https://www.sentinelone.com/feed/', category: 'news', label: 'SentinelOne', color: '#00d4ff' },
        { url: 'https://securelist.com/feed/', category: 'news', label: 'Securelist (Kaspersky)', color: '#00d4ff' },
        { url: 'https://feeds.fortinet.com/fortinet/blog/threat-research', category: 'news', label: 'Fortinet Threat Research', color: '#00d4ff' },
        { url: 'https://unit42.paloaltonetworks.com/feed/', category: 'news', label: 'Palo Alto Unit 42', color: '#00d4ff' }
    ];

    const TAG_PATTERNS = [
        { regex: /CVE-\d{4}-\d{4,}/gi, tag: 'CVE' },
        { regex: /ransomware/gi, tag: 'Ransomware' },
        { regex: /zero[- ]day/gi, tag: 'Zero-Day' },
        { regex: /\bAPT\b/g, tag: 'APT' },
        { regex: /exploit/gi, tag: 'Exploit' },
        { regex: /phishing/gi, tag: 'Phishing' },
        { regex: /malware/gi, tag: 'Malware' },
        { regex: /vulnerability|vulnerabilities/gi, tag: 'Vulnerability' },
        { regex: /patch|update/gi, tag: 'Patch' },
        { regex: /botnet/gi, tag: 'Botnet' },
        { regex: /supply[- ]chain/gi, tag: 'Supply Chain' },
        { regex: /data[- ]breach/gi, tag: 'Data Breach' },
        { regex: /ddos|denial.of.service/gi, tag: 'DDoS' },
        { regex: /backdoor/gi, tag: 'Backdoor' },
        { regex: /critical/gi, tag: 'Critical' }
    ];

    let allItems = [];
    let activeCategory = 'all';
    let activeVendor = null;
    let searchQuery = '';

    // ── Fetch feeds ──────────────────────────────────────────────
    async function fetchFeed(feed) {
        try {
            const url = `${RSS_PROXY}&url=${encodeURIComponent(feed.url)}&count=20`;
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.status !== 'ok' || !data.items) return [];
            return data.items.map(item => ({
                title: stripHtml(item.title || ''),
                description: stripHtml(item.description || '').substring(0, 300),
                link: item.link || '#',
                pubDate: item.pubDate || '',
                category: feed.category,
                source: feed.label,
                sourceColor: feed.color,
                tags: extractTags(item.title + ' ' + (item.description || '')),
                matchedAssets: findMatchedAssets(item.title + ' ' + (item.description || ''))
            }));
        } catch (e) {
            console.warn('Feed fetch failed:', feed.label, e);
            return [];
        }
    }

    function stripHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').trim();
    }

    function extractTags(text) {
        var tags = [];
        TAG_PATTERNS.forEach(function (p) {
            if (p.regex.test(text)) {
                tags.push(p.tag);
            }
            p.regex.lastIndex = 0;
        });
        return [...new Set(tags)];
    }

    function findMatchedAssets(text) {
        var textLower = text.toLowerCase();
        var matched = [];
        (window.TI_OWNED_ASSETS || []).forEach(function (asset) {
            asset.terms.forEach(function (term) {
                if (term.length >= 3 && textLower.indexOf(term) !== -1) {
                    var key = asset.vendor + ' ' + asset.model;
                    if (matched.indexOf(key) === -1) {
                        matched.push(key);
                    }
                }
            });
        });
        return matched;
    }

    async function loadAllFeeds() {
        var results = await Promise.allSettled(FEEDS.map(fetchFeed));
        allItems = [];
        results.forEach(function (r) {
            if (r.status === 'fulfilled') {
                allItems = allItems.concat(r.value);
            }
        });
        // Sort by date descending
        allItems.sort(function (a, b) {
            return new Date(b.pubDate) - new Date(a.pubDate);
        });
        updateStats();
        renderFeed();
        renderRelevantSidebar();
        document.getElementById('tiLoading').style.display = 'none';
    }

    // ── Stats ────────────────────────────────────────────────────
    function updateStats() {
        document.getElementById('statTotal').textContent = allItems.length;
        document.getElementById('statMitre').textContent = allItems.filter(function (i) { return i.category === 'mitre'; }).length;
        document.getElementById('statCisa').textContent = allItems.filter(function (i) { return i.category === 'cisa'; }).length;
        document.getElementById('statNews').textContent = allItems.filter(function (i) { return i.category === 'news'; }).length;
        document.getElementById('statRelevant').textContent = allItems.filter(function (i) { return i.matchedAssets.length > 0; }).length;
    }

    // ── Filtering ────────────────────────────────────────────────
    function getFilteredItems() {
        return allItems.filter(function (item) {
            if (activeCategory !== 'all' && item.category !== activeCategory) return false;
            if (activeVendor) {
                var vendorLower = activeVendor.toLowerCase();
                var textLower = (item.title + ' ' + item.description).toLowerCase();
                if (textLower.indexOf(vendorLower) === -1) return false;
            }
            if (searchQuery) {
                var q = searchQuery.toLowerCase();
                var haystack = (item.title + ' ' + item.description + ' ' + item.tags.join(' ')).toLowerCase();
                if (haystack.indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    // ── Render Feed ──────────────────────────────────────────────
    function renderFeed() {
        var container = document.getElementById('tiFeed');
        var items = getFilteredItems();

        // Remove existing cards (keep loading div)
        var cards = container.querySelectorAll('.ti-card');
        cards.forEach(function (c) { c.remove(); });

        if (items.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'ti-card ti-empty';
            empty.innerHTML = '<p style="text-align:center;opacity:0.5;padding:2rem;">No matching threats found.</p>';
            container.appendChild(empty);
            return;
        }

        items.forEach(function (item) {
            var card = document.createElement('div');
            card.className = 'ti-card';
            if (item.matchedAssets.length > 0) {
                card.classList.add('ti-card-relevant');
            }

            var dateStr = '';
            if (item.pubDate) {
                var d = new Date(item.pubDate);
                dateStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            var tagsHtml = item.tags.map(function (t) {
                return '<span class="ti-tag">' + escHtml(t) + '</span>';
            }).join('');

            var matchHtml = '';
            if (item.matchedAssets.length > 0) {
                matchHtml = '<div class="ti-card-match"><span class="ti-match-icon">&#9888;</span> ' +
                    item.matchedAssets.map(function (a) { return escHtml(a); }).join(', ') +
                    '</div>';
            }

            card.innerHTML =
                '<div class="ti-card-header">' +
                    '<span class="ti-source-badge" style="background:' + item.sourceColor + ';">' + escHtml(item.source) + '</span>' +
                    '<span class="ti-card-date">' + escHtml(dateStr) + '</span>' +
                '</div>' +
                '<a href="' + escAttr(item.link) + '" target="_blank" rel="noopener" class="ti-card-title">' + escHtml(item.title) + '</a>' +
                '<p class="ti-card-desc">' + escHtml(item.description) + '</p>' +
                (tagsHtml ? '<div class="ti-card-tags">' + tagsHtml + '</div>' : '') +
                matchHtml;

            container.appendChild(card);
        });
    }

    // ── Render Relevant Sidebar ──────────────────────────────────
    function renderRelevantSidebar() {
        var container = document.getElementById('tiRelevant');
        var emptyEl = document.getElementById('tiRelevantEmpty');
        var relevant = allItems.filter(function (i) { return i.matchedAssets.length > 0; });

        if (relevant.length === 0) {
            if (emptyEl) emptyEl.textContent = 'No threats matching your devices found.';
            return;
        }
        if (emptyEl) emptyEl.style.display = 'none';

        // Show max 15 items
        relevant.slice(0, 15).forEach(function (item) {
            var el = document.createElement('div');
            el.className = 'ti-sidebar-item';
            el.innerHTML =
                '<a href="' + escAttr(item.link) + '" target="_blank" rel="noopener" class="ti-sidebar-title">' + escHtml(item.title) + '</a>' +
                '<div class="ti-sidebar-meta">' +
                    '<span class="ti-source-badge ti-source-sm" style="background:' + item.sourceColor + ';">' + escHtml(item.source) + '</span>' +
                    '<span class="ti-sidebar-date">' + escHtml(formatRelative(item.pubDate)) + '</span>' +
                '</div>' +
                '<div class="ti-sidebar-affects">' +
                    '<span class="ti-match-icon">&#9881;</span> ' +
                    item.matchedAssets.map(function (a) { return escHtml(a); }).join(', ') +
                '</div>';
            container.appendChild(el);
        });
    }

    function formatRelative(dateStr) {
        if (!dateStr) return '';
        var diff = Date.now() - new Date(dateStr).getTime();
        var hours = Math.floor(diff / 3600000);
        if (hours < 1) return 'Just now';
        if (hours < 24) return hours + 'h ago';
        var days = Math.floor(hours / 24);
        if (days === 1) return '1 day ago';
        return days + ' days ago';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }

    // ── Event Listeners ──────────────────────────────────────────
    function init() {
        // Category filters
        document.querySelectorAll('.ti-pill[data-category]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.ti-pill[data-category]').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                activeCategory = btn.dataset.category;
                renderFeed();
            });
        });

        // Vendor filters (toggle)
        document.querySelectorAll('.ti-pill-vendor').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.classList.contains('active')) {
                    btn.classList.remove('active');
                    activeVendor = null;
                } else {
                    document.querySelectorAll('.ti-pill-vendor').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    activeVendor = btn.dataset.vendor;
                }
                renderFeed();
            });
        });

        // Search
        var searchInput = document.getElementById('tiSearch');
        var debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                searchQuery = searchInput.value.trim();
                renderFeed();
            }, 300);
        });

        // Load feeds
        loadAllFeeds();

        // Auto-refresh every 10 minutes
        setInterval(function () {
            loadAllFeeds();
        }, 600000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
