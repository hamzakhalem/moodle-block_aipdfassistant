<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.



/**
 * Strings for component 'block_aipdfassistant', language 'ar'.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_ask'] = 'الإجابة عن أسئلتك حوله';
$string['action_explain'] = 'شرحه';
$string['action_filename'] = 'إخبارك باسمه';
$string['action_quiz'] = 'اختبارك فيه';
$string['action_speak'] = 'قراءته بصوت مسموع';
$string['action_summarize'] = 'تلخيصه';
$string['action_translate'] = 'ترجمته';
$string['aipdfassistant:addinstance'] = 'إضافة كتلة مساعد PDF الذكي';
$string['aipdfassistant:myaddinstance'] = 'إضافة كتلة مساعد PDF الذكي إلى لوحة القيادة';
$string['aipdfassistant:use'] = 'استخدام مساعد PDF الذكي';
$string['aipdfassistant:usespeech'] = 'الاستماع إلى الإجابات بصوت مسموع';
$string['aipdfassistant:viewreport'] = 'عرض تقرير استخدام مساعد PDF الذكي';
$string['blocktitle_default'] = 'مساعد الدراسة';
$string['config_title'] = 'عنوان الكتلة';
$string['error_auth'] = 'رفضت خدمة الذكاء الاصطناعي مفتاح API. على المدير التحقق منه.';
$string['error_badmodel'] = 'لم يتم العثور على النموذج المحدد. على المدير التحقق من اسم النموذج.';
$string['error_badrequest'] = 'رفضت خدمة الذكاء الاصطناعي الطلب.';
$string['error_badresponse'] = 'أرسلت خدمة الذكاء الاصطناعي ردا غير متوقع.';
$string['error_docmissing'] = 'لم يعد هذا المستند متاحا. أرفقه من جديد.';
$string['error_emptyaudio'] = 'لم تُرجع خدمة الصوت أي ملف صوتي.';
$string['error_emptylength'] = 'انقطعت الإجابة قبل أن تبدأ. على المدير زيادة الحد الأقصى لطول الإجابة.';
$string['error_emptyresponse'] = 'أعادت الخدمة إجابة فارغة. حاول مرة أخرى.';
$string['error_encrypted'] = 'هذا الملف محمي بكلمة مرور ولا يمكن قراءته.';
$string['error_generic'] = 'حدث خطأ ما. يرجى المحاولة مرة أخرى.';
$string['error_network'] = 'تعذر الوصول إلى خدمة الذكاء الاصطناعي. تحقق من اتصال الخادم بالإنترنت.';
$string['error_networkblocked'] = 'منعت مودل الاتصال (إعدادات أمان HTTP). على المدير السماح بهذا المضيف.';
$string['error_noextractabletext'] = 'لم يتم العثور على نص مقروء. قد يكون الملف صورا ممسوحة ضوئيا، والتعرف الضوئي غير مدعوم بعد.';
$string['error_nofile'] = 'لم يتم استلام أي ملف.';
$string['error_notconfigured'] = 'المساعد غير مُعدّ. يرجى التواصل مع مدير المنصة.';
$string['error_nothingtoread'] = 'لا يوجد ما يُقرأ بصوت مسموع.';
$string['error_notpdf'] = 'هذا الملف ليس بصيغة PDF.';
$string['error_providerlimit'] = 'خدمة الذكاء الاصطناعي مشغولة أو نفدت حصتها. حاول لاحقا.';
$string['error_ratelimit'] = 'وصلت إلى حد الاستخدام. حاول مجددا بعد حوالي {$a} دقيقة.';
$string['error_timeout'] = 'استغرقت قراءة هذا الملف وقتا طويلا فتم إيقافها. جرّب ملفا أصغر أو أبسط.';
$string['error_toolarge'] = 'حجم الملف أكبر من الحد المسموح، أو أنه يتضخم إلى بيانات كثيرة جدا عند قراءته.';
$string['error_toolong'] = 'هذا المستند أطول مما يتحمله النموذج المحدد.';
$string['error_ttsdisabled'] = 'الإخراج الصوتي معطّل في هذه المنصة.';
$string['error_ttsnotconfigured'] = 'الإخراج الصوتي غير مُعدّ.';
$string['error_unavailable'] = 'خدمة الذكاء الاصطناعي غير متاحة مؤقتا. حاول بعد قليل.';
$string['error_unreadable'] = 'تعذرت قراءة الملف.';
$string['error_unsupportedtype'] = 'نوع هذا الملف غير مدعوم. استخدم ملف PDF أو Word (.docx)؛ ملفات .doc القديمة يجب حفظها بصيغة .docx أولا.';
$string['filenamereply'] = 'اسم الملف هو **{$a->name}** ({$a->pages} صفحة).';
$string['gender_female'] = 'امرأة';
$string['gender_male'] = 'رجل';
$string['js_assistant'] = 'المساعد';
$string['js_attach'] = 'إرفاق ملف PDF أو Word';
$string['js_audioFailed'] = 'تعذر تشغيل الصوت.';
$string['js_browserVoice'] = 'صوت المتصفح';
$string['js_cached'] = 'إجابة محفوظة';
$string['js_clear'] = 'محادثة جديدة';
$string['js_collapse'] = 'إغلاق العرض الموسع';
$string['js_copied'] = 'تم النسخ';
$string['js_copy'] = 'نسخ';
$string['js_courseFiles'] = 'ملفات هذا المقرر';
$string['js_docAttached'] = 'تم إرفاق المستند: {$a}';
$string['js_dropHint'] = 'أو انقر لاختيار ملف · حتى {$a} ميغابايت';
$string['js_dropTitle'] = 'أسقط ملف PDF أو Word هنا';
$string['js_emptyHint'] = 'أضف ملف PDF أو Word لتلخيصه أو شرحه أو ترجمته أو الاستماع إليه. يمكنك الكتابة أو الإملاء بالعربية أو الفرنسية أو الإنجليزية.';
$string['js_emptyTitle'] = 'طاولة دراستك';
$string['js_errorGeneric'] = 'حدث خطأ ما. يرجى المحاولة مرة أخرى.';
$string['js_errorSize'] = 'حجم هذا الملف أكبر من {$a} ميغابايت.';
$string['js_errorType'] = 'يرجى اختيار ملف PDF أو Word (.docx).';
$string['js_expand'] = 'تكبير';
$string['js_explain'] = 'اشرح';
$string['js_filename'] = 'اسم الملف';
$string['js_listen'] = 'استمع';
$string['js_loading'] = 'جارٍ التحميل…';
$string['js_lowtext'] = 'تم العثور على نص قليل في هذا الملف. لا يمكن قراءة الصفحات الممسوحة ضوئيا.';
$string['js_mic'] = 'إملاء صوتي';
$string['js_micStop'] = 'إيقاف الإملاء';
$string['js_noDocs'] = 'لا يوجد شيء هنا بعد.';
$string['js_noticePartial'] = 'المستند طويل، لذلك تعتمد هذه الإجابة على أجزاء موزعة منه.';
$string['js_noticeTranslate'] = 'تمت ترجمة الجزء الأول فقط من المستند.';
$string['js_page'] = 'صفحة واحدة';
$string['js_pages'] = '{$a} صفحة';
$string['js_pickDocument'] = 'ملفاتي والمقرر';
$string['js_placeholder'] = 'اسأل عن المستند…';
$string['js_placeholderNoDoc'] = 'أرفق ملف PDF أو Word أو اسأل أي شيء…';
$string['js_quiz'] = 'اختبرني';
$string['js_reading'] = 'جارٍ قراءة الملف…';
$string['js_ready'] = 'جاهز. اختر إجراء من الأسفل أو اطرح سؤالا.';
$string['js_recent'] = 'الملفات الأخيرة';
$string['js_removeDoc'] = 'إزالة المستند';
$string['js_retry'] = 'حاول مجددا';
$string['js_send'] = 'إرسال';
$string['js_speak'] = 'اقرأ بصوت مسموع';
$string['js_speaking'] = 'جارٍ القراءة بصوت مسموع…';
$string['js_stop'] = 'إيقاف';
$string['js_summarize'] = 'لخّص';
$string['js_thinking'] = 'جارٍ التفكير…';
$string['js_translate'] = 'ترجم';
$string['js_translateTo'] = 'الترجمة إلى';
$string['js_uploadFailed'] = 'فشل الرفع';
$string['js_uploading'] = 'جارٍ الرفع…';
$string['js_you'] = 'أنت';
$string['lang_auto'] = 'تلقائي';
$string['link_report'] = 'تقرير الاستخدام';
$string['link_test'] = 'اختبار الاتصال';
$string['link_tools_desc'] = '<a href="{$a->test}">اختبار الاتصال</a> · <a href="{$a->report}">تقرير الاستخدام</a>';
$string['needdoc'] = 'أرفق ملف PDF أو Word أولا، وبعدها يمكنني {$a}.';
$string['notconfigured_admin'] = 'المساعد غير مُعدّ بعد، لذلك يرى المستخدمون هذا الإشعار بدل المحادثة: التلخيص والشرح والترجمة والاختبارات والأسئلة والصوت كلها غير متاحة حتى يتم إعداد مزود الذكاء الاصطناعي. اختر المزود وأدخل مفتاح API من حسابك لديه (أو عنوان خادم Ollama الخاص بك).';
$string['notconfigured_user'] = 'المساعد غير متاح حاليا. يرجى مراسلة مدير المنصة.';
$string['opensettings'] = 'فتح الإعدادات';
$string['pluginname'] = 'مساعد PDF الذكي';
$string['privacy:metadata:audio'] = 'ملفات الصوت المولدة للإجابات المقروءة';
$string['privacy:metadata:cache'] = 'الإجابات المخزنة مؤقتا (ملخصات وشروحات وترجمات واختبارات) المولدة من مستند المستخدم';
$string['privacy:metadata:cache:action'] = 'الإجراء الذي أنتج الإجابة';
$string['privacy:metadata:cache:response'] = 'نص الإجابة المولدة';
$string['privacy:metadata:cache:timecreated'] = 'وقت توليد الإجابة';
$string['privacy:metadata:cache:userid'] = 'المستخدم الذي طلب الإجابة';
$string['privacy:metadata:docs'] = 'النص المستخرج من ملفات PDF وWord التي أرفقها المستخدم';
$string['privacy:metadata:docs:courseid'] = 'المقرر الذي استُخدمت فيه الكتلة';
$string['privacy:metadata:docs:filename'] = 'اسم الملف';
$string['privacy:metadata:docs:textcontent'] = 'النص المستخرج من الملف';
$string['privacy:metadata:docs:timecreated'] = 'وقت إضافة المستند';
$string['privacy:metadata:docs:userid'] = 'مالك المستند';
$string['privacy:metadata:externalai'] = 'يُرسل نص ملف PDF ورسائل المستخدم إلى مزود الذكاء الاصطناعي الذي اختاره المدير لإنتاج الإجابات';
$string['privacy:metadata:externalai:text'] = 'نص المستند والمحادثة';
$string['privacy:metadata:externalvoice'] = 'يُرسل نص الإجابة إلى مزود الصوت الذي اختاره المدير لإنتاج الملف الصوتي';
$string['privacy:metadata:externalvoice:text'] = 'النص المراد قراءته';
$string['privacy:metadata:log'] = 'سجل الاستخدام المستعمل لتحديد المعدل والتقارير';
$string['privacy:metadata:log:action'] = 'الإجراء المطلوب';
$string['privacy:metadata:log:provider'] = 'مزود الذكاء الاصطناعي المستخدم';
$string['privacy:metadata:log:timecreated'] = 'وقت الطلب';
$string['privacy:metadata:log:userid'] = 'المستخدم الذي قدّم الطلب';
$string['privacy:path:activity'] = 'النشاط';
$string['privacy:path:cache'] = 'الإجابات المخزنة';
$string['privacy:path:documents'] = 'المستندات';
$string['report_action'] = 'الإجراء';
$string['report_byaction'] = 'حسب الإجراء';
$string['report_cached'] = 'إجابات محفوظة';
$string['report_errors'] = 'أخطاء';
$string['report_last30'] = 'آخر 30 يوما';
$string['report_none'] = 'لا يوجد نشاط بعد.';
$string['report_provider'] = 'المزود';
$string['report_recent'] = 'آخر الأحداث';
$string['report_requests'] = 'الطلبات';
$string['report_status'] = 'الحالة';
$string['report_time'] = 'الوقت';
$string['report_title'] = 'مساعد PDF الذكي: تقرير الاستخدام';
$string['report_tokens'] = 'الرموز (دخل / خرج)';
$string['report_topusers'] = 'أكثر المستخدمين نشاطا';
$string['report_user'] = 'المستخدم';
$string['setting_allowcoursepdfs'] = 'السماح باختيار ملفات PDF وWord من المقرر';
$string['setting_allowcoursepdfs_desc'] = 'يرى المستخدمون فقط الملفات التي يمكنهم فتحها أصلا.';
$string['setting_apikey'] = 'مفتاح API';
$string['setting_apikey_desc'] = 'يُخزَّن على الخادم ولا يُرسل أبدا إلى المتصفحات. اتركه فارغا مع Ollama أو الخوادم بدون مصادقة.';
$string['setting_baseurl'] = 'عنوان API الأساسي';
$string['setting_baseurl_desc'] = 'اختياري. مطلوب مع "مخصص". مثال لـ Ollama: http://localhost:11434/v1';
$string['setting_cachedays'] = 'مدة حفظ الإجابات (أيام)';
$string['setting_cachedays_desc'] = 'تُقدَّم الملخصات والشروحات والترجمات المتطابقة لنفس الملف من النسخة المحفوظة.';
$string['setting_extracttimeout'] = 'المهلة الزمنية لقراءة الملف (ثوان)';
$string['setting_extracttimeout_desc'] = 'تتوقف قراءة المستند بعد هذه المدة، حتى لا يستطيع ملف تالف أو خبيث إبقاء الخادم مشغولا. تشمل برنامج pdftotext والقارئ المدمج.';
$string['setting_limit_day'] = 'الطلبات لكل مستخدم في اليوم';
$string['setting_limit_day_desc'] = '0 = بلا حد.';
$string['setting_limit_hour'] = 'الطلبات لكل مستخدم في الساعة';
$string['setting_limit_hour_desc'] = '0 = بلا حد. لا تُحتسب الإجابات المحفوظة.';
$string['setting_maxchunks'] = 'الحد الأقصى للأجزاء لكل طلب';
$string['setting_maxchunks_desc'] = 'كل جزء يمثل استدعاء API واحدا، لذا يحدّ هذا من تكلفة المستند الطويل.';
$string['setting_maxcontextchars'] = 'عدد أحرف المستند لكل طلب';
$string['setting_maxcontextchars_desc'] = 'تُلخَّص المستندات الأطول على أجزاء ثم تُدمج. كل 4 أحرف تقريبا تساوي رمزا واحدا.';
$string['setting_maxdecompressedmb'] = 'أكبر حجم بيانات يمكن أن يتضخم إليه الملف (ميغابايت)';
$string['setting_maxdecompressedmb_desc'] = 'حماية من قنابل فك الضغط: يُرفض ملف PDF إذا كانت تدفقاته المضغوطة ستتضخم إلى أكثر من هذا المجموع (القارئ المدمج)، أو إذا أنتج pdftotext نصا أكبر منه. وتخضع ملفات Word للحد نفسه.';
$string['setting_maxdocchars'] = 'الحد الأقصى للأحرف المحفوظة لكل مستند';
$string['setting_maxdocchars_desc'] = '';
$string['setting_maxfilesize'] = 'الحجم الأقصى للملف (ميغابايت)';
$string['setting_maxfilesize_desc'] = 'يحدّه أيضا حد الرفع في PHP ومودل.';
$string['setting_maxtokens'] = 'الحد الأقصى لطول الإجابة (رموز)';
$string['setting_maxtokens_desc'] = 'تستهلك نماذج الاستدلال جزءا من هذا الحد في التفكير؛ استخدم 2000 على الأقل.';
$string['setting_model'] = 'النموذج';
$string['setting_model_desc'] = 'معرّف النموذج كما يسميه المزود تماما. اتركه فارغا لاستخدام الافتراضي المقترح. تتغير أسماء النماذج كثيرا، راجع وثائق المزود.';
$string['setting_pdftotextpath'] = 'مسار pdftotext';
$string['setting_pdftotextpath_desc'] = 'يعطي pdftotext من Poppler أفضل نص. إن لم يكن مثبتا يُستخدم قارئ PHP المدمج تلقائيا. اتركه فارغا لتعطيله.';
$string['setting_provider'] = 'المزود';
$string['setting_provider_desc'] = 'يعمل OpenAI وClaude وGemini وDeepSeek بمفتاح API فقط. يقبل Ollama و"مخصص" أي خادم متوافق مع OpenAI. الخوادم المحلية أو الشبكات الخاصة يجب السماح بها أولا من: إدارة الموقع > عام > الأمان > أمان HTTP.';
$string['setting_responselang'] = 'لغة الإجابة';
$string['setting_responselang_desc'] = '"تلقائي" يجيب بلغة السؤال.';
$string['setting_retentiondays'] = 'مدة الاحتفاظ بالمستندات والصوت (أيام)';
$string['setting_retentiondays_desc'] = 'تحذف مهمة مجدولة العناصر الأقدم.';
$string['setting_systemextra'] = 'تعليمات إضافية';
$string['setting_systemextra_desc'] = 'تُضاف إلى كل طلب، مثال: "استخدم مفردات طلبة السنة الأولى في علم الأحياء."';
$string['setting_temperature'] = 'درجة الحرارة';
$string['setting_temperature_desc'] = 'القيمة الأقل أكثر دقة. تُتجاهل مع النماذج التي لا تسمح بتغييرها.';
$string['setting_timeout'] = 'مهلة الطلب (ثوان)';
$string['setting_timeout_desc'] = '';
$string['setting_tts_apikey'] = 'مفتاح API للصوت';
$string['setting_tts_apikey_desc'] = 'اتركه فارغا لإعادة استخدام مفتاح مزود النص إذا كان المزودان من الشركة نفسها.';
$string['setting_tts_baseurl'] = 'عنوان API الأساسي للصوت';
$string['setting_tts_baseurl_desc'] = 'اختياري. مطلوب مع "مخصص".';
$string['setting_tts_gender'] = 'الصوت';
$string['setting_tts_gender_desc'] = 'يختار المدير ما إذا كانت الإجابات تُقرأ بصوت رجل أو امرأة.';
$string['setting_tts_maxchars'] = 'الحد الأقصى للأحرف المقروءة صوتيا';
$string['setting_tts_maxchars_desc'] = 'تُقرأ الإجابات الأطول حتى هذا الحد للتحكم في التكلفة.';
$string['setting_tts_model'] = 'نموذج الصوت';
$string['setting_tts_model_desc'] = 'فارغ = الافتراضي.';
$string['setting_tts_provider'] = 'مزود الصوت';
$string['setting_tts_provider_desc'] = 'لا توفر Claude وDeepSeek واجهة صوت، لذا اقرنهما هنا بـ OpenAI أو Gemini أو ElevenLabs. صوت المتصفح مجاني لكن جودته واختيار رجل/امرأة يعتمدان على جهاز المستخدم.';
$string['setting_tts_voice_female'] = 'معرّف الصوت النسائي';
$string['setting_tts_voice_female_desc'] = 'فارغ = الافتراضي.';
$string['setting_tts_voice_male'] = 'معرّف الصوت الرجالي';
$string['setting_tts_voice_male_desc'] = 'فارغ = الافتراضي.';
$string['settings_behaviour'] = 'السلوك والحدود';
$string['settings_docs'] = 'المستندات';
$string['settings_gettingstarted'] = 'قبل البدء: حسابات مزودي الذكاء الاصطناعي';
$string['settings_gettingstarted_desc'] = '<p>لا تتضمن هذه الإضافة أي ذكاء اصطناعي. فهي ترسل نص المستند الذي يرفقه المستخدم وأسئلته إلى خدمة ذكاء اصطناعي <strong>تختارها وتدفع ثمنها أنت</strong>. تحتاج إلى حساب لدى هذا المزود ومفتاح API منه:</p><ul><li><strong>OpenAI</strong>: أنشئ حسابا على platform.openai.com وأضف رصيدا ثم أنشئ مفتاح API. اشتراك ChatGPT لا يتضمن الوصول إلى API.</li><li><strong>Claude (Anthropic)</strong>: أنشئ حسابا على console.anthropic.com وأضف رصيدا وأنشئ مفتاح API.</li><li><strong>Google Gemini</strong>: أنشئ مفتاحا على aistudio.google.com/apikey. توجد فئة مجانية محدودة، والاستخدام المدفوع تحاسب عليه Google.</li><li><strong>DeepSeek</strong>: أنشئ حسابا على platform.deepseek.com وأضف رصيدا وأنشئ مفتاح API.</li><li><strong>Ollama أو أي خادم متوافق مع OpenAI</strong>: لا حاجة إلى حساب. شغّل الخادم بنفسك (انظر ollama.com) وأدخل عنوانه كعنوان أساسي، واسمح بهذا المضيف في إدارة الموقع &gt; عام &gt; الأمان &gt; أمان HTTP إذا كان على localhost أو شبكة خاصة.</li></ul><p><strong>قبل إعداد المزود</strong> تعرض الكتلة إشعارا بدل المحادثة، فلا يمكن تلخيص أو شرح أو ترجمة أو اختبار أو الإجابة عن أي شيء، ولا يُرسل شيء إلى أي مزود. يظهر زر الاستماع فقط بعد اختيار صوت أدناه (صوت المتصفح المجاني لا يحتاج إلى حساب). يحاسب المزودون حسب الاستخدام: استعمل الحدود في قسم السلوك للتحكم في التكلفة، وتأكد أن سياسة الخصوصية لديك تذكر المزود الذي تختاره لأن نص المستند يُرسل إليه.</p>';
$string['settings_text'] = 'مزود النص بالذكاء الاصطناعي';
$string['settings_text_desc'] = 'اختر خدمة الذكاء الاصطناعي التي تكتب الملخصات والشروحات والترجمات والإجابات. كل شيء يمر عبر واجهة API الخاصة بها.';
$string['settings_tools'] = 'أدوات';
$string['settings_voice'] = 'الصوت (تحويل النص إلى كلام)';
$string['settings_voice_desc'] = 'اختياري. يتيح للمستخدمين الاستماع إلى الإجابات. بدون صوت يُخفى زر الاستماع. اختر واجهة صوت API لجودة عالية (تحتاج إلى حساب ومفتاح API لدى المزود)، أو صوت المتصفح المجاني الذي لا يحتاج إلى حساب.';
$string['speaklast'] = 'هذه النسخة الصوتية من إجابتي الأخيرة.';
$string['task_cleanup'] = 'تنظيف بيانات مساعد PDF الذكي';
$string['test_failed'] = 'فشل';
$string['test_latency'] = 'زمن الاستجابة: {$a} ثانية';
$string['test_notconfigured'] = 'مزود النص غير مُعدّ بعد.';
$string['test_ok'] = 'نجاح';
$string['test_pdf_heading'] = 'قراءة PDF وWord';
$string['test_pdftotext_missing'] = 'لم يتم العثور على pdftotext؛ سيُستخدم قارئ PHP المدمج (ملفات PDF النصية فقط).';
$string['test_pdftotext_ok'] = 'pdftotext متاح.';
$string['test_prompt'] = 'أجب بجملة قصيرة واحدة بالعربية والفرنسية والإنجليزية تقول إن الاتصال يعمل.';
$string['test_provider'] = 'المزود: {$a}';
$string['test_run_text'] = 'تشغيل اختبار النص';
$string['test_run_voice'] = 'تشغيل اختبار الصوت';
$string['test_text_heading'] = 'مزود النص';
$string['test_title'] = 'مساعد PDF الذكي: اختبار الاتصال';
$string['test_voice_browser'] = 'الصوت يستخدم المتصفح، لا شيء للاختبار على الخادم.';
$string['test_voice_disabled'] = 'الصوت معطّل.';
$string['test_voice_heading'] = 'مزود الصوت';
$string['test_voice_sample'] = 'Hello! This is a test of the study assistant voice. مرحبا، هذا اختبار صوت مساعد الدراسة.';
$string['tts_browser'] = 'صوت المتصفح (مجاني)';
$string['tts_custom'] = 'مخصص (متوافق مع OpenAI)';
$string['tts_elevenlabs'] = 'ElevenLabs';
$string['tts_gemini'] = 'Google Gemini';
$string['tts_none'] = 'معطّل';
$string['tts_openai'] = 'OpenAI';
