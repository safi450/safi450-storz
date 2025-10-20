#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
مستخرج بيانات التطبيقات من موقع Mobilltna.org
استخراج البيانات من أول 30 صفحة مع تأخير آمن
"""

import requests
from bs4 import BeautifulSoup
import json
import time
from urllib.parse import urljoin
import logging
from datetime import datetime

# إعداد Logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class MobilltnaSScraper:
    def __init__(self):
        self.base_url = "https://mobilltna.org"
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        self.apps_data = {
            "mobile_apps": [],
            "games": [],
            "tv_apps": []
        }
        self.app_id_counter = {
            "mobile_apps": 1,
            "games": 1,
            "tv_apps": 1
        }
        self.session = requests.Session()
        self.session.headers.update(self.headers)

    def safe_request(self, url, retries=3):
        """محاولة الحصول على الصفحة مع إعادة محاولة"""
        for attempt in range(retries):
            try:
                response = self.session.get(url, timeout=10)
                response.raise_for_status()
                return response
            except requests.exceptions.RequestException as e:
                logger.warning(f"محاولة {attempt + 1}/{retries} فشلت للـ URL: {url}")
                if attempt < retries - 1:
                    time.sleep(2 ** attempt)  # تأخير تصاعدي
                else:
                    logger.error(f"فشل الحصول على: {url}")
                    return None

    def extract_app_icon(self, name):
        """اختيار أيقونة بناءً على اسم التطبيق"""
        icons = {
            "player": "fa-video",
            "video": "fa-video",
            "music": "fa-music",
            "photo": "fa-image",
            "camera": "fa-camera",
            "editor": "fa-edit",
            "browse": "fa-globe",
            "browser": "fa-globe",
            "game": "fa-gamepad",
            "puzzle": "fa-puzzle-piece",
            "chess": "fa-chess",
            "sport": "fa-trophy",
            "tv": "fa-tv",
            "movie": "fa-film",
            "series": "fa-theater-masks",
            "book": "fa-book",
            "read": "fa-book",
            "news": "fa-newspaper",
            "weather": "fa-cloud",
            "map": "fa-map",
            "navigate": "fa-map",
            "tool": "fa-tools",
            "util": "fa-wrench",
        }
        
        name_lower = name.lower()
        for keyword, icon in icons.items():
            if keyword in name_lower:
                return icon
        return "fa-star"

    def parse_rating(self, rating_text):
        """تحليل تقييم من النص"""
        if not rating_text:
            return 4.5
        try:
            rating = float(rating_text.split()[0])
            return min(5.0, max(1.0, rating))
        except:
            return 4.5

    def parse_reviews_count(self, reviews_text):
        """تحليل عدد التقييمات"""
        if not reviews_text:
            return 100
        try:
            text = reviews_text.lower().replace(',', '')
            if 'k' in text:
                return int(float(text.replace('k', '')) * 1000)
            return int(text.split()[0])
        except:
            return 100

    def scrape_category(self, category_name, category_url, max_pages=30):
        """استخراج البيانات من فئة معينة"""
        logger.info(f"🔄 بدء استخراج البيانات من: {category_name}")
        
        for page in range(1, max_pages + 1):
            try:
                # بناء رابط الصفحة
                if page == 1:
                    url = category_url
                else:
                    url = f"{category_url}?page={page}"
                
                logger.info(f"  📄 الصفحة {page}/{max_pages}: {url}")
                
                response = self.safe_request(url)
                if not response:
                    logger.warning(f"  ⚠️ تخطي الصفحة {page}")
                    continue
                
                soup = BeautifulSoup(response.content, 'html.parser')
                
                # البحث عن عناصر التطبيقات (يختلف حسب بنية الموقع)
                app_items = soup.find_all('div', class_='app-item')
                
                if not app_items:
                    # محاولة بحثية بديلة
                    app_items = soup.find_all('div', {'data-app': True})
                
                if not app_items:
                    logger.warning(f"  ❌ لم يتم العثور على تطبيقات في الصفحة {page}")
                    continue
                
                apps_found = 0
                for item in app_items:
                    try:
                        # استخراج البيانات
                        name_elem = item.find(['h2', 'h3', 'a', 'span'], class_=['app-name', 'title', 'name'])
                        name = name_elem.get_text(strip=True) if name_elem else "Unknown App"
                        
                        desc_elem = item.find(['p', 'span'], class_=['description', 'desc', 'summary'])
                        description = desc_elem.get_text(strip=True) if desc_elem else "تطبيق مميز"
                        
                        # إذا كان الوصف طويل جداً، قصره
                        if len(description) > 150:
                            description = description[:150] + "..."
                        
                        rating_elem = item.find(['span', 'div'], class_=['rating', 'rate', 'stars'])
                        rating = self.parse_rating(rating_elem.get_text() if rating_elem else None)
                        
                        reviews_elem = item.find(['span', 'div'], class_=['reviews', 'count', 'votes'])
                        reviews = self.parse_reviews_count(reviews_elem.get_text() if reviews_elem else None)
                        
                        # تحديد الفئة
                        if "game" in category_name.lower() or "لعبة" in category_name.lower():
                            cat_key = "games"
                        elif "tv" in category_name.lower() or "تلفاز" in category_name.lower():
                            cat_key = "tv_apps"
                        else:
                            cat_key = "mobile_apps"
                        
                        app_obj = {
                            "id": self.app_id_counter[cat_key],
                            "name": name,
                            "description": description,
                            "icon": self.extract_app_icon(name),
                            "rating": rating,
                            "reviews": reviews,
                            "category": "app",
                            "download_link": f"https://mobilltna.org/app/{name.replace(' ', '-').lower()}"
                        }
                        
                        self.apps_data[cat_key].append(app_obj)
                        self.app_id_counter[cat_key] += 1
                        apps_found += 1
                        
                    except Exception as e:
                        logger.debug(f"    ⚠️ خطأ في تحليل تطبيق: {e}")
                        continue
                
                logger.info(f"  ✅ تم استخراج {apps_found} تطبيق من الصفحة {page}")
                
                # تأخير آمن بين الطلبات
                if page < max_pages:
                    time.sleep(1.5)
                
            except Exception as e:
                logger.error(f"  ❌ خطأ في معالجة الصفحة {page}: {e}")
                continue
        
        logger.info(f"✅ انتهى استخراج {category_name}: {len(self.apps_data[cat_key])} تطبيق")

    def scrape_all(self):
        """استخراج البيانات من جميع الفئات"""
        logger.info("=" * 60)
        logger.info("🚀 بدء استخراج بيانات التطبيقات من Mobilltna.org")
        logger.info("=" * 60)
        
        categories = [
            ("تطبيقات موبايل", f"{self.base_url}/apps", "mobile_apps"),
            ("الألعاب", f"{self.base_url}/games", "games"),
            ("تطبيقات التلفاز", f"{self.base_url}/tv", "tv_apps"),
        ]
        
        for cat_name, cat_url, cat_key in categories:
            self.scrape_category(cat_name, cat_url, max_pages=30)
            time.sleep(2)  # تأخير بين الفئات
        
        return self.apps_data

    def save_to_json(self, filename='data/apps_new.json'):
        """حفظ البيانات في ملف JSON"""
        try:
            filepath = filename
            with open(filepath, 'w', encoding='utf-8') as f:
                json.dump(self.apps_data, f, ensure_ascii=False, indent=2)
            
            total = (len(self.apps_data["mobile_apps"]) + 
                    len(self.apps_data["games"]) + 
                    len(self.apps_data["tv_apps"]))
            
            logger.info(f"✅ تم حفظ البيانات في: {filepath}")
            logger.info(f"📊 الإجمالي: {total} تطبيق")
            logger.info(f"   • تطبيقات موبايل: {len(self.apps_data['mobile_apps'])}")
            logger.info(f"   • ألعاب: {len(self.apps_data['games'])}")
            logger.info(f"   • تطبيقات TV: {len(self.apps_data['tv_apps'])}")
            
        except Exception as e:
            logger.error(f"❌ خطأ في حفظ الملف: {e}")

if __name__ == "__main__":
    try:
        logger.info(f"⏰ بدء الاستخراج: {datetime.now()}")
        
        scraper = MobilltnaSScraper()
        data = scraper.scrape_all()
        scraper.save_to_json('data/apps_new.json')
        
        logger.info(f"⏰ انتهى الاستخراج: {datetime.now()}")
        logger.info("=" * 60)
        
    except KeyboardInterrupt:
        logger.warning("\n⚠️ تم إيقاف البرنامج من قبل المستخدم")
    except Exception as e:
        logger.error(f"❌ خطأ غير متوقع: {e}", exc_info=True)