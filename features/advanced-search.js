/**
 * محرك البحث المتقدم - Advanced Search Engine
 * يتولى البحث الفوري والتصفية والترتيب
 */

class AdvancedSearch {
    constructor() {
        this.apps = [];
        this.results = [];
        this.searchTerm = '';
        this.filters = {
            category: 'all',
            minRating: 0,
            sortBy: 'popular'
        };
        this.init();
    }

    /**
     * تحميل البيانات من JSON
     */
    async loadApps() {
        try {
            const response = await fetch('data/apps.json');
            const data = await response.json();
            
            // دمج جميع الفئات
            this.apps = [
                ...this.flattenCategory(data.mobile_apps || [], 'mobile'),
                ...this.flattenCategory(data.games || [], 'games'),
                ...this.flattenCategory(data.tv_apps || [], 'tv')
            ];
            
            return this.apps;
        } catch (error) {
            console.error('❌ خطأ في تحميل البيانات:', error);
            return [];
        }
    }

    /**
     * تحويل فئة إلى مصفوفة مسطحة
     */
    flattenCategory(items, category) {
        return items.map(item => ({
            ...item,
            category_slug: category
        }));
    }

    /**
     * البحث الفوري
     */
    search(term, filters = {}) {
        this.searchTerm = term.toLowerCase();
        this.filters = { ...this.filters, ...filters };

        let results = this.apps;

        // 1️⃣ البحث النصي
        if (this.searchTerm) {
            results = results.filter(app => 
                app.name.toLowerCase().includes(this.searchTerm) ||
                app.description.toLowerCase().includes(this.searchTerm) ||
                (app.full_description && app.full_description.toLowerCase().includes(this.searchTerm))
            );
        }

        // 2️⃣ تصفية حسب الفئة
        if (this.filters.category !== 'all') {
            results = results.filter(app => app.category_slug === this.filters.category);
        }

        // 3️⃣ تصفية حسب التقييم
        if (this.filters.minRating > 0) {
            results = results.filter(app => (app.rating || 0) >= this.filters.minRating);
        }

        // 4️⃣ الترتيب
        results = this.sortResults(results, this.filters.sortBy);

        this.results = results;
        return results;
    }

    /**
     * ترتيب النتائج
     */
    sortResults(items, sortBy) {
        const sorted = [...items];

        switch (sortBy) {
            case 'rating':
                return sorted.sort((a, b) => (b.rating || 0) - (a.rating || 0));
            
            case 'reviews':
                return sorted.sort((a, b) => (b.reviews || 0) - (a.reviews || 0));
            
            case 'name':
                return sorted.sort((a, b) => a.name.localeCompare(b.name, 'ar'));
            
            case 'new':
                return sorted.reverse();
            
            case 'popular':
            default:
                return sorted.sort((a, b) => ((b.reviews || 0) + (b.rating || 0)) - ((a.reviews || 0) + (a.rating || 0)));
        }
    }

    /**
     * الحصول على الاقتراحات
     */
    getSuggestions(term, limit = 5) {
        if (!term || term.length < 2) return [];

        const lowerTerm = term.toLowerCase();
        const matches = this.apps
            .filter(app => app.name.toLowerCase().includes(lowerTerm))
            .slice(0, limit)
            .map(app => app.name);

        // إزالة التكرار
        return [...new Set(matches)];
    }

    /**
     * إحصائيات البحث
     */
    getStats() {
        return {
            total: this.apps.length,
            results: this.results.length,
            categories: {
                mobile: this.apps.filter(a => a.category_slug === 'mobile').length,
                games: this.apps.filter(a => a.category_slug === 'games').length,
                tv: this.apps.filter(a => a.category_slug === 'tv').length
            },
            avgRating: (this.apps.reduce((sum, a) => sum + (a.rating || 0), 0) / this.apps.length).toFixed(1)
        };
    }

    /**
     * إعادة تعيين البحث
     */
    reset() {
        this.searchTerm = '';
        this.filters = {
            category: 'all',
            minRating: 0,
            sortBy: 'popular'
        };
        this.results = this.apps;
        return this.results;
    }

    /**
     * التهيئة
     */
    async init() {
        await this.loadApps();
    }
}

// 🌐 إنشاء نسخة عامة
const searchEngine = new AdvancedSearch();

// انتظر التحميل الكامل
document.addEventListener('DOMContentLoaded', async () => {
    await searchEngine.init();
    initSearchUI();
});

/**
 * 🎨 واجهة البحث
 */
function initSearchUI() {
    const searchInput = document.getElementById('searchInput');
    const categoriesFilter = document.getElementById('categoriesFilter');
    const ratingFilter = document.getElementById('ratingFilter');
    const sortBy = document.getElementById('sortBy');
    const suggestionsBox = document.getElementById('searchSuggestions');
    const resultsContainer = document.getElementById('searchResults');
    const resetBtn = document.getElementById('resetSearch');

    if (!searchInput) return; // إذا لم توجد عناصر البحث

    // البحث الفوري مع تأخير (Debounce)
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const term = e.target.value;

        // إظهار الاقتراحات
        if (term.length >= 2) {
            const suggestions = searchEngine.getSuggestions(term);
            showSuggestions(suggestions, suggestionsBox);
        } else {
            suggestionsBox.style.display = 'none';
        }

        searchTimeout = setTimeout(() => {
            performSearch();
        }, 300);
    });

    // الاقتراحات
    if (suggestionsBox) {
        document.addEventListener('click', (e) => {
            if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
                suggestionsBox.style.display = 'none';
            }
        });
    }

    // التصفية والترتيب
    if (categoriesFilter) categoriesFilter.addEventListener('change', performSearch);
    if (ratingFilter) ratingFilter.addEventListener('change', performSearch);
    if (sortBy) sortBy.addEventListener('change', performSearch);

    // إعادة تعيين
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            searchInput.value = '';
            categoriesFilter.value = 'all';
            ratingFilter.value = '0';
            sortBy.value = 'popular';
            searchEngine.reset();
            displayResults(searchEngine.results);
        });
    }

    /**
     * تنفيذ البحث
     */
    function performSearch() {
        const filters = {
            category: categoriesFilter?.value || 'all',
            minRating: parseFloat(ratingFilter?.value || 0),
            sortBy: sortBy?.value || 'popular'
        };

        const results = searchEngine.search(searchInput.value, filters);
        displayResults(results);
        updateStats(results);
    }

    /**
     * عرض النتائج
     */
    function displayResults(apps) {
        if (!resultsContainer) return;

        if (apps.length === 0) {
            resultsContainer.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>لم نجد نتائج</h3>
                    <p>حاول بحثاً آخر أو تصفية مختلفة</p>
                </div>
            `;
            return;
        }

        resultsContainer.innerHTML = apps.map(app => `
            <div class="app-result-card">
                <div class="app-icon">
                    <i class="fas ${app.icon || 'fa-mobile-alt'}"></i>
                </div>
                <div class="app-info">
                    <h3>${app.name}</h3>
                    <p class="app-desc">${app.description}</p>
                    <div class="app-meta">
                        <span class="rating">
                            <i class="fas fa-star"></i> ${app.rating || 'N/A'}
                        </span>
                        <span class="reviews">
                            <i class="fas fa-comment"></i> ${app.reviews || 0} تقييم
                        </span>
                        <span class="category-badge">${getCategoryName(app.category_slug)}</span>
                    </div>
                </div>
                <a href="${app.download_link || '#'}" class="btn-download-mini">
                    <i class="fas fa-download"></i>
                </a>
            </div>
        `).join('');

        // إضافة تأثيرات الفيد
        document.querySelectorAll('.app-result-card').forEach((card, i) => {
            card.style.animation = `fadeIn 0.3s ease forwards`;
            card.style.animationDelay = `${i * 50}ms`;
        });
    }

    /**
     * عرض الاقتراحات
     */
    function showSuggestions(suggestions, box) {
        if (!suggestions.length) {
            box.style.display = 'none';
            return;
        }

        box.innerHTML = suggestions
            .map(s => `<div class="suggestion-item" onclick="document.getElementById('searchInput').value='${s}'; performSearch();">${s}</div>`)
            .join('');
        box.style.display = 'block';
    }

    /**
     * تحديث الإحصائيات
     */
    function updateStats(results) {
        const statsEl = document.getElementById('searchStats');
        if (statsEl) {
            statsEl.innerHTML = `
                عرض ${results.length} من ${searchEngine.apps.length} تطبيق
            `;
        }
    }

    /**
     * حصول على اسم الفئة
     */
    function getCategoryName(slug) {
        const names = {
            mobile: '📱 تطبيقات',
            games: '🎮 ألعاب',
            tv: '📺 تلفاز'
        };
        return names[slug] || slug;
    }
}
