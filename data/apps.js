// تحميل وعرض التطبيقات من JSON
let allApps = [];

// دالة تحميل البيانات من JSON
async function loadAppsData(category) {
    try {
        // استخدم مسار نسبي بسيط
        let fetchPath = './data/apps.json';
        
        // إذا كان هناك فشل، جرب بدون ./
        let response = await fetch(fetchPath).catch(() => fetch('data/apps.json'));
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // تحديد أي بيانات سيتم استخدامها بناءً على الفئة
        if (category === 'mobile') {
            allApps = data.mobile_apps;
        } else if (category === 'games') {
            allApps = data.games;
        } else if (category === 'tv') {
            allApps = data.tv_apps;
        }
        
        console.log(`✅ تم تحميل ${allApps.length} ${category === 'mobile' ? 'تطبيق هاتف' : category === 'games' ? 'لعبة' : 'تطبيق تلفزيون'}`);
        renderApps(allApps);
    } catch (error) {
        console.error('❌ خطأ في تحميل البيانات:', error);
        console.warn('تأكد من أن ملف data/apps.json موجود وصحيح');
    }
}

// دالة لعرض التطبيقات
function renderApps(apps) {
    const appsList = document.querySelector('.apps-list');
    if (!appsList) return;
    
    // حذف العناصر القديمة (احتفاظ بالإعلانات)
    const oldItems = appsList.querySelectorAll('.app-item');
    oldItems.forEach(item => item.remove());
    
    // إضافة التطبيقات
    let htmlContent = '';
    apps.forEach((app, index) => {
        // إضافة إعلان كل 3 تطبيقات (بطريقة غير مزعجة)
        if (index > 0 && index % 3 === 0) {
            htmlContent += `
                <div class="ad-container" style="grid-column: 1 / -1; text-align: center; margin: 20px 0;">
                    <!-- Google AdSense Responsive Ad -->
                    <ins class="adsbygoogle"
                         style="display:block; text-align:center;"
                         data-ad-layout="in-article"
                         data-ad-format="fluid"
                         data-ad-client="ca-pub-YOUR_PUBLISHER_ID"
                         data-ad-slot="YOUR_AD_SLOT_ID"></ins>
                    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
                </div>
            `;
        }
        
        // إنشاء نجوم التقييم
        const fullStars = Math.floor(app.rating);
        const hasHalfStar = app.rating % 1 !== 0;
        let starsHtml = '';
        
        for (let i = 0; i < fullStars; i++) {
            starsHtml += '<i class="fas fa-star"></i>';
        }
        if (hasHalfStar) {
            starsHtml += '<i class="fas fa-star-half-alt"></i>';
        }
        
        htmlContent += `
            <a href="app-details.html?id=${app.id}" class="app-item" style="text-decoration: none; color: inherit; cursor: pointer;">
                <div class="app-icon">
                    ${app.image 
                        ? `<img src="${app.image}" alt="${app.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">` 
                        : `<i class="fas ${app.icon}"></i>`
                    }
                </div>
                <div class="app-name">${app.name}</div>
                <div class="app-description">${app.description}</div>
                <div class="app-rating">
                    <span class="stars">${starsHtml}</span>
                    <span class="rating-text">${app.rating} (${app.reviews.toLocaleString('ar-SA')})</span>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                    <div class="app-download-btn" style="display: block; flex: 1; text-align: center;"><i class="fas fa-info-circle"></i> التفاصيل</div>
                </div>
            </a>
        `;
    });
    
    // إضافة إعلان أخير
    htmlContent += `
        <div class="ad-container" style="grid-column: 1 / -1; text-align: center; margin: 20px 0;">
            <!-- Google AdSense Responsive Ad -->
            <ins class="adsbygoogle"
                 style="display:block; text-align:center;"
                 data-ad-layout="in-article"
                 data-ad-format="fluid"
                 data-ad-client="ca-pub-YOUR_PUBLISHER_ID"
                 data-ad-slot="YOUR_AD_SLOT_ID"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
        </div>
    `;
    
    appsList.innerHTML = htmlContent;
}

// دالة البحث محسّنة
function filterApps() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const appItems = document.querySelectorAll('.app-item');
    let visibleCount = 0;
    
    appItems.forEach(item => {
        const appName = item.querySelector('.app-name').textContent.toLowerCase();
        const appDescription = item.querySelector('.app-description').textContent.toLowerCase();
        
        if (appName.includes(searchInput) || appDescription.includes(searchInput)) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // إخفاء الإعلانات إذا لم يكن هناك نتائج
    const adContainers = document.querySelectorAll('.ad-container');
    adContainers.forEach(ad => {
        ad.style.display = visibleCount > 0 ? '' : 'none';
    });
}

// تفعيل البحث عند الكتابة
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterApps);
    }
    
    // معالج الروابط بديل إذا كان هناك رابط تحميل مباشر
    document.addEventListener('click', (e) => {
        if (e.target.closest('.direct-download-btn')) {
            e.preventDefault();
            const downloadBtn = e.target.closest('.direct-download-btn');
            const downloadUrl = downloadBtn.href;
            const appName = downloadBtn.dataset.appName;
            
            // طريقة 1: استخدم الطريقة التقليدية
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = appName + '.apk';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
});