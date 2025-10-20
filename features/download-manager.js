/**
 * نظام إدارة التحميل المتقدم
 * يدعم التحميل من Dropbox مع متابعة التقدم
 */

class DownloadManager {
    constructor() {
        this.downloads = new Map();
    }

    /**
     * بدء التحميل
     * @param {string} appName - اسم التطبيق
     * @param {string} downloadUrl - رابط التحميل
     * @param {string} appId - معرّف التطبيق
     */
    async startDownload(appName, downloadUrl, appId) {
        try {
            // إنشاء عنصر التحميل
            const downloadId = `download-${appId}-${Date.now()}`;
            
            // إظهار شاشة التحميل
            this.showDownloadOverlay(appName, downloadId);

            // بدء التحميل
            const response = await fetch(downloadUrl, {
                method: 'GET',
                mode: 'no-cors'
            });

            if (!response.ok && response.status !== 0) {
                throw new Error(`خطأ HTTP: ${response.status}`);
            }

            // حفظ معلومات التحميل
            this.downloads.set(downloadId, {
                appName,
                url: downloadUrl,
                startTime: Date.now(),
                status: 'downloading'
            });

            // تحميل الملف
            const blob = await response.blob();
            this.downloadFile(blob, `${appName}.apk`, downloadId);

        } catch (error) {
            console.error('خطأ في التحميل:', error);
            this.showError(appName, error.message);
        }
    }

    /**
     * تحميل الملف فعلياً
     */
    downloadFile(blob, filename, downloadId) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        // تحديث الحالة
        const download = this.downloads.get(downloadId);
        if (download) {
            download.status = 'completed';
            download.endTime = Date.now();
            this.showSuccess(download, downloadId);
        }
    }

    /**
     * إظهار شاشة التحميل
     */
    showDownloadOverlay(appName, downloadId) {
        const overlay = document.createElement('div');
        overlay.id = downloadId;
        overlay.className = 'download-overlay';
        overlay.innerHTML = `
            <div class="download-modal">
                <div class="spinner"></div>
                <h3>جاري تحميل ${appName}</h3>
                <p class="download-status">الرجاء الانتظار...</p>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    /**
     * إظهار رسالة النجاح
     */
    showSuccess(download, downloadId) {
        const overlay = document.getElementById(downloadId);
        if (overlay) {
            overlay.innerHTML = `
                <div class="download-modal success">
                    <div class="checkmark">✓</div>
                    <h3>تم التحميل بنجاح!</h3>
                    <p>${download.appName}</p>
                    <p class="download-time">
                        الوقت: ${Math.round((download.endTime - download.startTime) / 1000)}ث
                    </p>
                    <button onclick="this.closest('.download-overlay').remove()">إغلاق</button>
                </div>
            `;

            // إغلاق تلقائي بعد 3 ثواني
            setTimeout(() => {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.remove(), 300);
            }, 3000);
        }
    }

    /**
     * إظهار رسالة الخطأ
     */
    showError(appName, errorMessage) {
        const notification = document.createElement('div');
        notification.className = 'download-error-toast';
        notification.innerHTML = `
            <strong>خطأ!</strong>
            <p>${appName}: ${errorMessage}</p>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    /**
     * الحصول على حالة التحميل
     */
    getDownloadStatus(downloadId) {
        return this.downloads.get(downloadId);
    }

    /**
     * حذف تاريخ التحميل
     */
    clearDownloadHistory() {
        this.downloads.clear();
    }
}

// إنشاء كائن عام للاستخدام
const downloadManager = new DownloadManager();

// دالة مساعدة لبدء التحميل
function downloadApp(appName, downloadUrl, appId) {
    downloadManager.startDownload(appName, downloadUrl, appId);
}