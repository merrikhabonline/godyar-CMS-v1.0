/* global Fuse */
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarMinimize = document.getElementById('sidebarMinimize');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const sidebarSearch = document.getElementById('sidebarSearch');
    const searchResults = document.getElementById('searchResults');

    function clearChildren(el){ while(el?.firstChild) el.removeChild(el.firstChild); }
    function safeSameOriginHref(href){
        try{ const u=new URL(href, window.location.origin); return (u.origin===window.location.origin) ? (u.pathname+u.search+u.hash) : '#'; }catch(e){ return '#'; }
    }
    function appendHighlighted(container, text, term){
        const t = String(text || '');
        const q = String(term || '').trim();
        if(!q){ container.textContent = t; return; }
        const lower = t.toLowerCase();
        const ql = q.toLowerCase();
        let i = 0;
        while(true){
            const idx = lower.indexOf(ql, i);
            if(idx === -1) break;
            if(idx > i) container.appendChild(document.createTextNode(t.slice(i, idx)));
            const mark = document.createElement('mark');
            mark.style.background = 'rgba(56, 189, 248, 0.3)';
            mark.style.color = 'var(--sidebar-accent)';
            mark.style.padding = '0 2px';
            mark.style.borderRadius = '2px';
            mark.textContent = t.slice(idx, idx + q.length);
            container.appendChild(mark);
            i = idx + q.length;
        }
        if(i < t.length) container.appendChild(document.createTextNode(t.slice(i)));
    }

    // تهيئة بيانات البحث
    const menuItems = Array.from(document.querySelectorAll('.sidebar-item-card')).map(item => {
        const link = item.querySelector('a');
        const labelNode = item.querySelector('.sidebar-item-label');
        const subNode = item.querySelector('.sidebar-item-sub');
        
        return {
            element: item,
            label: labelNode ? labelNode.textContent.trim() : '',
            sub: subNode ? subNode.textContent.trim() : '',
            keywords: item.getAttribute('data-search') || '',
            href: link ? link.getAttribute('href') : '',
            title: link ? link.getAttribute('title') : ''
        };
    });

    // فتح/إغلاق الشريط في الجوال
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            this.setAttribute('aria-expanded', sidebar.classList.contains('open'));
        });
    }

    // تصغير/تكبير الشريط
    if (sidebarMinimize) {
        sidebarMinimize.addEventListener('click', function() {
            sidebar.classList.toggle('minimized');
            const icon = this.querySelector('use');
            if (sidebar.classList.contains('minimized')) {
                icon.className = 'fa-solid fa-indent';
                this.title = 'تكبير الشريط';
                this.setAttribute('aria-label', 'تكبير الشريط الجانبي');
            } else {
                icon.className = 'fa-solid fa-outdent';
                this.title = 'تصغير الشريط';
                this.setAttribute('aria-label', 'تصغير الشريط الجانبي');
            }
        });
    }

    // الوضع الليلي
    if (darkModeToggle) {
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.setAttribute('data-theme', 'dark');
            darkModeToggle.querySelector('use');
            darkModeToggle.title = 'الوضع النهاري';
            darkModeToggle.setAttribute('aria-label', 'الوضع النهاري');
        }
        darkModeToggle.addEventListener('click', function() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('darkMode', 'disabled');
                const href = '/assets/icons/gdy-icons.svg#moon';
                const useEl = this.querySelector('use');
                if(useEl){ useEl.setAttribute('href', href); useEl.setAttribute('xlink:href', href); }
                this.title = 'الوضع الليلي';
                this.setAttribute('aria-label', 'الوضع الليلي');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('darkMode', 'enabled');
                const href = '/assets/icons/gdy-icons.svg#sun';
                const useEl = this.querySelector('use');
                if(useEl){ useEl.setAttribute('href', href); useEl.setAttribute('xlink:href', href); }
                this.title = 'الوضع النهاري';
                this.setAttribute('aria-label', 'الوضع النهاري');
            }
        });
    }

    // البحث في القوائم
    if (sidebarSearch) {
        let fuse;
        
        if (typeof Fuse !== 'undefined') {
            fuse = new Fuse(menuItems, {
                keys: [
                    { name: 'label', weight: 0.5 },
                    { name: 'keywords', weight: 0.3 },
                    { name: 'sub', weight: 0.2 }
                ],
                threshold: 0.3,
                includeScore: true
            });
        }

        sidebarSearch.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length === 0) {
                searchResults.style.display = 'none';
                clearChildren(searchResults);
                return;
            }

            let results;
            
            if (fuse) {
                results = fuse.search(searchTerm).map(result => result.item);
            } else {
                const term = searchTerm.toLowerCase();
                results = menuItems.filter(item => 
                    item.label.toLowerCase().includes(term) ||
                    item.keywords.toLowerCase().includes(term) ||
                    item.sub.toLowerCase().includes(term)
                );
            }

            displaySearchResults(results, searchTerm);
        });

        function displaySearchResults(results, searchTerm) {
            clearChildren(searchResults);

            if (!results || results.length === 0) {
                searchResults.style.display = 'none';
                return;
            }

            results.slice(0, 12).forEach(result => {
                const item = document.createElement('a');
                item.className = 'sidebar-search-result';
                item.href = safeSameOriginHref(result.href || '#');

                const icon = document.createElement('span');
                icon.className = 'sidebar-search-result-icon';
                icon.textContent = '🔎';
                item.appendChild(icon);

                const content = document.createElement('div');
                content.className = 'sidebar-search-result-content';

                const title = document.createElement('div');
                title.className = 'sidebar-search-result-title';
                appendHighlighted(title, result.label || '', searchTerm);
                content.appendChild(title);

                if (result.sub) {
                    const sub = document.createElement('div');
                    sub.className = 'sidebar-search-result-sub';
                    sub.textContent = String(result.sub);
                    content.appendChild(sub);
                }

                item.appendChild(content);
                searchResults.appendChild(item);
            });

            searchResults.style.display = 'block';
        }

        // التنقل باللوحة
        sidebarSearch.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const items = searchResults.querySelectorAll('.sidebar-search-result');
                if (items.length === 0) return;
                
                let currentIndex = -1;
                items.forEach((item, index) => {
                    if (item === document.activeElement) {
                        currentIndex = index;
                    }
                });
                
                let nextIndex;
                if (e.key === 'ArrowDown') {
                    nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                } else {
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                }
                
                items[nextIndex].focus();
            }
        });

        document.addEventListener('click', function(e) {
            if (!sidebarSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchResults.style.display = 'none';
                sidebarSearch.blur();
            }
        });
    }

    // طي/فتح الأقسام
    document.querySelectorAll('.sidebar-heading').forEach(heading => {
        heading.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section');
            const sectionUl = document.getElementById(`section-${sectionId}`);
            if (!sectionUl) return;
            
            const isCollapsing = !this.classList.contains('collapsed');
            this.classList.toggle('collapsed');
            sectionUl.classList.toggle('collapsed');
            
            this.setAttribute('aria-expanded', !isCollapsing);
            sectionUl.setAttribute('aria-hidden', isCollapsing);
        });
        
        const sectionId = heading.getAttribute('data-section');
        const sectionUl = document.getElementById(`section-${sectionId}`);
        if (sectionUl) {
            const isCollapsed = heading.classList.contains('collapsed');
            heading.setAttribute('aria-expanded', !isCollapsed);
            sectionUl.setAttribute('aria-hidden', isCollapsed);
        }
    });

    // فتح القسم النشط
    const activeItem = document.querySelector('.sidebar-item-card.active');
    if (activeItem) {
        const section = activeItem.closest('.sidebar-section');
        if (section) {
            const heading = section.querySelector('.sidebar-heading');
            const list = section.querySelector('.sidebar-list');
            if (heading && list) {
                heading.classList.remove('collapsed');
                list.classList.remove('collapsed');
                heading.setAttribute('aria-expanded', 'true');
                list.setAttribute('aria-hidden', 'false');
            }
        }
        const content = document.querySelector('.sidebar-content');
        if (content) {
            setTimeout(() => {
                const rect = activeItem.getBoundingClientRect();
                const contentRect = content.getBoundingClientRect();
                content.scrollTop += (rect.top - contentRect.top) - content.clientHeight / 2 + activeItem.offsetHeight / 2;
            }, 100);
        }
    }
});

// تحسين إمكانية الوصول للشريط المصغر
document.addEventListener('keydown', function(e) {
    if (e.key === 'Tab' && document.getElementById('adminSidebar')?.classList.contains('minimized')) {
        const focused = document.activeElement;
        if (focused.closest('.sidebar')) {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.remove('minimized');
            setTimeout(() => {
                if (document.activeElement === focused) {
                    sidebar.classList.add('minimized');
                }
            }, 2000);
        }
    }
});