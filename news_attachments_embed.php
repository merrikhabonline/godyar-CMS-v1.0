<?php
declare(strict_types=1);

/**
 * Frontend snippet: عرض مرفقات الخبر داخل الصفحة + زر حفظ.
 *
 * يعرض قائمة المرفقات وزر "مشاهدة" لكل مرفق لفتح معاينة داخل نافذة منبثقة (Modal).
 *
 * أنواع المعاينة:
 * - PDF/TXT/RTF: iframe
 * - Images: img
 * - غير ذلك: رسالة بسيطة + زر حفظ
 *
 * الاستخدام داخل صفحة الخبر (بعد توفر PDO و $newsId):
 *   require_once __DIR__ . '/news_attachments_embed.php';
 *   gdy_render_news_attachments_embed($pdo, (int)$newsId);
 */

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('gdy_starts_with')) {
    function gdy_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function gdy_att_icon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return '📄';
        case 'doc':
        case 'docx': return '📝';
        case 'xls':
        case 'xlsx': return '📊';
        case 'ppt':
        case 'pptx': return '📽️';
        case 'zip':
        case 'rar':
        case '7z': return '🗜️';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'webp': return '🖼️';
        case 'txt':
        case 'rtf': return '📃';
        default: return '📎';
    }
}

function gdy_att_preview_meta(string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return [
        'ext' => $ext,
        'pdf' => $ext === 'pdf',
        'img' => in_array($ext, ['png','jpg','jpeg','gif','webp'], true),
        'txt' => in_array($ext, ['txt','rtf'], true),
    ];
}

/**
 * @param array $options
 *   - base_url: (string) إذا كان الموقع ليس على نفس الجذر. الافتراضي '/'
 *   - title: (string) عنوان صندوق المرفقات
 */
function gdy_render_news_attachments_embed(PDO $pdo, int $newsId, array $options = []): void {
    if ($newsId <= 0) return;

    $baseUrl = (string)($options['base_url'] ?? '/');
    $baseUrl = $baseUrl === '' ? '/' : $baseUrl;
    $title   = (string)($options['title'] ?? (function_exists('__') ? __('t_a2737af54c', 'المرفقات') : 'المرفقات'));

    // إذا جدول المرفقات غير موجود، لا نعرض شيئاً
    try {
        $exists = function_exists('gdy_db_table_exists') ? (gdy_db_table_exists($pdo, 'news_attachments') ? 1 : 0) : 0;
        if (!$exists) return;
    } catch (Throwable $e) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT id, original_name, file_path, mime_type, file_size
         FROM news_attachments
         WHERE news_id = :nid
         ORDER BY id DESC"
    );
    $stmt->execute([':nid' => $newsId]);
    $atts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$atts) return;

    $uid = 'gdyAtt' . $newsId . '_' . substr(hash('sha256', (string)$newsId . '|' . (string)count($atts)), 0, 6);

    // CSS بسيط بدون الاعتماد على Bootstrap
    echo "
<style>
";
    echo ".{$uid}-box{border:1px solid rgba(0,0,0,.12);border-radius:14px;padding:14px;margin:16px 0;background:#fff;max-width:100%;overflow:hidden}
";
    echo ".{$uid}-title{font-weight:700;margin:0 0 10px;font-size:16px}
";
    echo ".{$uid}-item{border:1px solid rgba(0,0,0,.10);border-radius:12px;padding:10px 10px;background:rgba(0,0,0,.02);margin:10px 0}
";
    echo ".{$uid}-row{display:flex;gap:10px;align-items:center;justify-content:space-between}
";
    echo ".{$uid}-name{display:flex;align-items:center;gap:8px;min-width:0}
";
    echo ".{$uid}-name span.fn{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70vw;display:inline-block}
";
    echo ".{$uid}-actions{display:flex;gap:8px;flex-shrink:0}
";
    echo ".{$uid}-btn{border:1px solid rgba(0,0,0,.18);border-radius:10px;padding:6px 10px;font-size:13px;cursor:pointer;background:#fff;text-decoration:none;color:#111;display:inline-flex;align-items:center;gap:6px}
";
    echo ".{$uid}-btn:hover{background:rgba(0,0,0,.04)}
";
    echo ".{$uid}-muted{color:#666;font-size:13px}
";

    // Modal styles
    echo ".{$uid}-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);padding:16px;z-index:99999}
";
    echo ".{$uid}-modal.open{display:flex}
";
    echo ".{$uid}-dialog{width:min(980px, 100%);max-height:92vh;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.35);overflow:hidden;display:flex;flex-direction:column}
";
    echo ".{$uid}-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 12px;border-bottom:1px solid rgba(0,0,0,.10)}
";
    echo ".{$uid}-hname{font-weight:700;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
";
    echo ".{$uid}-close{border:1px solid rgba(0,0,0,.18);border-radius:12px;background:#fff;cursor:pointer;padding:6px 10px;font-size:13px}
";
    echo ".{$uid}-close:hover{background:rgba(0,0,0,.04)}
";
    echo ".{$uid}-body{padding:12px;overflow:auto}
";
    echo ".{$uid}-frame{width:100%;height:72vh;min-height:420px;border:1px solid rgba(0,0,0,.10);border-radius:12px;background:#f7f7f7}
";
    echo ".{$uid}-img{max-width:100%;height:auto;border:1px solid rgba(0,0,0,.10);border-radius:12px;display:block;margin:0 auto}
";
    echo ".{$uid}-footer{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;padding:12px;border-top:1px solid rgba(0,0,0,.10)}
";
    echo "</style>
";

    echo '<div class="' . h($uid) . '-box">';
    echo '<div class="' . h($uid) . '-title">' . h($title) . '</div>';

    foreach ($atts as $att) {
        $name = (string)($att['original_name'] ?? '');
        $path = (string)($att['file_path'] ?? '');

        // حماية بسيطة: لا نعرض مسارات غريبة
        $trimPath = ltrim($path, '/');
        if ($trimPath === '' || !(gdy_starts_with($trimPath, 'uploads/') || gdy_starts_with($trimPath, 'storage/') || gdy_starts_with($trimPath, 'public/'))) {
            continue;
        }

        $url  = rtrim($baseUrl, '/') . '/' . $trimPath;
        $meta = gdy_att_preview_meta($name);

        $data = [
            'data-url'  => $url,
            'data-name' => $name,
            'data-ext'  => (string)$meta['ext'],
            'data-pdf'  => $meta['pdf'] ? '1' : '0',
            'data-img'  => $meta['img'] ? '1' : '0',
            'data-txt'  => $meta['txt'] ? '1' : '0',
        ];
        $dataAttr = '';
        foreach ($data as $k => $v) {
            $dataAttr .= ' ' . h($k) . '="' . h($v) . '"';
        }

        echo '<div class="' . h($uid) . '-item">';
        echo '  <div class="' . h($uid) . '-row">';
        echo '    <div class="' . h($uid) . '-name">';
        echo '      <span aria-hidden="true">' . h(gdy_att_icon($name)) . '</span>';
        echo '      <span class="fn">' . h($name) . '</span>';
        echo '    </div>';
        echo '    <div class="' . h($uid) . '-actions">';
        echo '      <button type="button" class="' . h($uid) . '-btn"' . $dataAttr . ' data-action="gdy-att-open" data-uid="' . h($uid) . '">👁️ مشاهدة</button>';
        echo '      <a class="' . h($uid) . '-btn" href="' . h($url) . '" download>⬇️ حفظ</a>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    echo '</div>';

    // Modal template
    echo '<div class="' . h($uid) . '-modal" id="' . h($uid) . '_modal" role="dialog" aria-modal="true" aria-hidden="true">';
    echo '  <div class="' . h($uid) . '-dialog" id="' . h($uid) . '_dialog">';
    echo '    <div class="' . h($uid) . '-header">';
    echo '      <div class="' . h($uid) . '-hname" id="' . h($uid) . '_m_name">المرفق</div>';
    echo '      <button type="button" class="' . h($uid) . '-close" data-action="gdy-att-close" data-uid="' . h($uid) . '">✖ إغلاق</button>';
    echo '    </div>';
    echo '    <div class="' . h($uid) . '-body" id="' . h($uid) . '_m_body"></div>';
    echo '    <div class="' . h($uid) . '-footer">';
    echo '      <a class="' . h($uid) . '-btn" id="' . h($uid) . '_m_download" href="#" download>⬇️ حفظ</a>';
    echo '      <button type="button" class="' . h($uid) . '-btn" data-action="gdy-att-close" data-uid="' . h($uid) . '">تم</button>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';

    // JS: فتح/إغلاق مودال + معاينة آمنة بدون innerHTML
    echo "
<script>
";
    echo "(function(){
";
    echo "  var uid = " . json_encode($uid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";
";
    echo "  var modal = document.getElementById(uid + '_modal');
";
    echo "  var dialog = document.getElementById(uid + '_dialog');
";
    echo "  var mName = document.getElementById(uid + '_m_name');
";
    echo "  var mBody = document.getElementById(uid + '_m_body');
";
    echo "  var mDl   = document.getElementById(uid + '_m_download');
";
    echo "  if(!modal || !dialog || !mBody || !mName || !mDl) return;
";
    echo "  function clearBody(){ while(mBody.firstChild) mBody.removeChild(mBody.firstChild); }
";
    echo "  function openModal(btn){
";
    echo "    var url = btn.getAttribute('data-url') || '';
";
    echo "    var name = btn.getAttribute('data-name') || 'المرفق';
";
    echo "    var isPdf = btn.getAttribute('data-pdf') === '1';
";
    echo "    var isImg = btn.getAttribute('data-img') === '1';
";
    echo "    var isTxt = btn.getAttribute('data-txt') === '1';
";
    echo "    mName.textContent = name;
";
    echo "    mDl.setAttribute('href', url);
";
    echo "    clearBody();
";
    echo "    if(isPdf || isTxt){
";
    echo "      var ifr = document.createElement('iframe');
";
    echo "      ifr.className = uid + '-frame';
";
    echo "      ifr.setAttribute('loading','lazy');
";
    echo "      ifr.setAttribute('src', url);
";
    echo "      mBody.appendChild(ifr);
";
    echo "    } else if(isImg){
";
    echo "      var img = document.createElement('img');
";
    echo "      img.className = uid + '-img';
";
    echo "      img.setAttribute('loading','lazy');
";
    echo "      img.setAttribute('src', url);
";
    echo "      img.setAttribute('alt', name);
";
    echo "      mBody.appendChild(img);
";
    echo "    } else {
";
    echo "      var box = document.createElement('div');
";
    echo "      box.className = uid + '-muted';
";
    echo "      box.style.padding = '6px 2px';
";
    echo "      box.textContent = 'هذا النوع لا يُعرض عادة داخل المتصفح. يمكنك تنزيله عبر زر حفظ.';
";
    echo "      mBody.appendChild(box);
";
    echo "    }
";
    echo "    modal.classList.add('open');
";
    echo "    modal.setAttribute('aria-hidden','false');
";
    echo "  }
";
    echo "  function closeModal(){
";
    echo "    modal.classList.remove('open');
";
    echo "    modal.setAttribute('aria-hidden','true');
";
    echo "    clearBody();
";
    echo "    mDl.setAttribute('href','#');
";
    echo "  }
";
    echo "  document.addEventListener('click', function(e){
";
    echo "    var openBtn = e.target && e.target.closest ? e.target.closest('button[data-action=\"gdy-att-open\"][data-uid=\"' + uid + '\"]') : null;
";
    echo "    if(openBtn){ e.preventDefault(); openModal(openBtn); return; }
";
    echo "    var closeBtn = e.target && e.target.closest ? e.target.closest('[data-action=\"gdy-att-close\"][data-uid=\"' + uid + '\"]') : null;
";
    echo "    if(closeBtn){ e.preventDefault(); closeModal(); return; }
";
    echo "    if(modal.classList.contains('open')){
";
    echo "      // إغلاق عند الضغط خارج الصندوق
";
    echo "      if(e.target === modal) closeModal();
";
    echo "    }
";
    echo "  }, true);
";
    echo "  document.addEventListener('keydown', function(e){
";
    echo "    if(e.key === 'Escape' && modal.classList.contains('open')) closeModal();
";
    echo "  });
";
    echo "})();
";
    echo "</script>
";
}
