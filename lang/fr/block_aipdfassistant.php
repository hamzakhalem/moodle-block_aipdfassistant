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
 * Strings for component 'block_aipdfassistant', language 'fr'.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_ask'] = 'répondre à vos questions';
$string['action_explain'] = 'l’expliquer';
$string['action_filename'] = 'vous donner son nom';
$string['action_quiz'] = 'vous interroger dessus';
$string['action_speak'] = 'le lire à voix haute';
$string['action_summarize'] = 'le résumer';
$string['action_translate'] = 'le traduire';
$string['aipdfassistant:addinstance'] = 'Ajouter un bloc Assistant PDF IA';
$string['aipdfassistant:myaddinstance'] = 'Ajouter un bloc Assistant PDF IA au tableau de bord';
$string['aipdfassistant:use'] = 'Utiliser l’Assistant PDF IA';
$string['aipdfassistant:usespeech'] = 'Écouter les réponses à voix haute';
$string['aipdfassistant:viewreport'] = 'Voir le rapport d’utilisation de l’Assistant PDF IA';
$string['blocktitle_default'] = 'Assistant d’étude';
$string['config_title'] = 'Titre du bloc';
$string['error_auth'] = 'Le service d’IA a refusé la clé API. L’administrateur doit la vérifier.';
$string['error_badmodel'] = 'Le modèle d’IA configuré est introuvable. L’administrateur doit vérifier son nom.';
$string['error_badrequest'] = 'Le service d’IA a rejeté la requête.';
$string['error_badresponse'] = 'Le service d’IA a renvoyé une réponse inattendue.';
$string['error_docmissing'] = 'Ce document n’est plus disponible. Joignez-le à nouveau.';
$string['error_emptyaudio'] = 'Le service vocal n’a renvoyé aucun audio.';
$string['error_emptylength'] = 'La réponse a été coupée avant de commencer. L’administrateur doit augmenter la longueur maximale.';
$string['error_emptyresponse'] = 'Le service d’IA a renvoyé une réponse vide. Réessayez.';
$string['error_encrypted'] = 'Ce PDF est protégé par un mot de passe et ne peut pas être lu.';
$string['error_generic'] = 'Une erreur est survenue. Veuillez réessayer.';
$string['error_network'] = 'Impossible de joindre le service d’IA. Vérifiez l’accès Internet du serveur.';
$string['error_networkblocked'] = 'Moodle a bloqué la connexion (sécurité HTTP). L’administrateur doit autoriser cet hôte.';
$string['error_noextractabletext'] = 'Aucun texte lisible. Le PDF est peut-être composé d’images scannées ; l’OCR n’est pas encore pris en charge.';
$string['error_nofile'] = 'Aucun fichier reçu.';
$string['error_notconfigured'] = 'L’assistant n’est pas configuré. Contactez votre administrateur.';
$string['error_nothingtoread'] = 'Il n’y a rien à lire à voix haute.';
$string['error_notpdf'] = 'Ce fichier n’est pas un PDF.';
$string['error_providerlimit'] = 'Le service d’IA est saturé ou son quota est épuisé. Réessayez plus tard.';
$string['error_ratelimit'] = 'Limite d’utilisation atteinte. Réessayez dans environ {$a} minute(s).';
$string['error_timeout'] = 'La lecture de ce fichier a pris trop de temps et a été interrompue. Essayez un fichier plus petit ou plus simple.';
$string['error_toolarge'] = 'Le fichier dépasse la taille autorisée, ou il se décompresse en trop de données à la lecture.';
$string['error_toolong'] = 'Ce document est trop long pour le modèle configuré.';
$string['error_ttsdisabled'] = 'La sortie vocale est désactivée sur ce site.';
$string['error_ttsnotconfigured'] = 'La sortie vocale n’est pas configurée.';
$string['error_unavailable'] = 'Le service d’IA est momentanément indisponible. Réessayez dans un instant.';
$string['error_unreadable'] = 'Le fichier n’a pas pu être lu.';
$string['error_unsupportedtype'] = 'Ce type de fichier n’est pas pris en charge. Utilisez un PDF ou un fichier Word (.docx) ; les anciens .doc doivent être enregistrés en .docx.';
$string['filenamereply'] = 'Le fichier s’appelle **{$a->name}** ({$a->pages} pages).';
$string['gender_female'] = 'Femme';
$string['gender_male'] = 'Homme';
$string['js_assistant'] = 'Assistant';
$string['js_attach'] = 'Joindre un PDF ou un fichier Word';
$string['js_audioFailed'] = 'Impossible de lire l’audio.';
$string['js_browserVoice'] = 'Voix du navigateur';
$string['js_cached'] = 'Réponse enregistrée';
$string['js_clear'] = 'Nouvelle conversation';
$string['js_collapse'] = 'Fermer l’affichage agrandi';
$string['js_copied'] = 'Copié';
$string['js_copy'] = 'Copier';
$string['js_courseFiles'] = 'Fichiers de ce cours';
$string['js_docAttached'] = 'Document joint : {$a}';
$string['js_dropHint'] = 'ou cliquez pour choisir un fichier · {$a} Mo maximum';
$string['js_dropTitle'] = 'Déposez un PDF ou un fichier Word ici';
$string['js_emptyHint'] = 'Ajoutez un PDF ou un fichier Word pour le résumer, l’expliquer, le traduire ou l’écouter. Écrivez ou dictez en arabe, français ou anglais.';
$string['js_emptyTitle'] = 'Votre bureau d’étude';
$string['js_errorGeneric'] = 'Une erreur est survenue. Veuillez réessayer.';
$string['js_errorSize'] = 'Ce fichier dépasse {$a} Mo.';
$string['js_errorType'] = 'Veuillez choisir un fichier PDF ou Word (.docx).';
$string['js_expand'] = 'Agrandir';
$string['js_explain'] = 'Expliquer';
$string['js_filename'] = 'Nom du fichier';
$string['js_listen'] = 'Écouter';
$string['js_loading'] = 'Chargement…';
$string['js_lowtext'] = 'Peu de texte trouvé dans ce fichier. Les pages scannées ne peuvent pas être lues.';
$string['js_mic'] = 'Dicter';
$string['js_micStop'] = 'Arrêter la dictée';
$string['js_noDocs'] = 'Rien ici pour le moment.';
$string['js_noticePartial'] = 'Le document est long : cette réponse repose sur des parties réparties du texte.';
$string['js_noticeTranslate'] = 'Seule la première partie du document a été traduite.';
$string['js_page'] = '1 page';
$string['js_pages'] = '{$a} pages';
$string['js_pickDocument'] = 'Mes fichiers et cours';
$string['js_placeholder'] = 'Posez une question sur le document…';
$string['js_placeholderNoDoc'] = 'Joignez un PDF ou un Word, ou posez une question…';
$string['js_quiz'] = 'M’interroger';
$string['js_reading'] = 'Lecture du PDF…';
$string['js_ready'] = 'Prêt. Choisissez une action ci-dessous ou posez une question.';
$string['js_recent'] = 'Fichiers récents';
$string['js_removeDoc'] = 'Retirer le document';
$string['js_retry'] = 'Réessayer';
$string['js_send'] = 'Envoyer';
$string['js_speak'] = 'Lire à voix haute';
$string['js_speaking'] = 'Lecture à voix haute…';
$string['js_stop'] = 'Arrêter';
$string['js_summarize'] = 'Résumer';
$string['js_thinking'] = 'Réflexion…';
$string['js_translate'] = 'Traduire';
$string['js_translateTo'] = 'Traduire en';
$string['js_uploadFailed'] = 'Échec de l’envoi';
$string['js_uploading'] = 'Envoi en cours…';
$string['js_you'] = 'Vous';
$string['lang_auto'] = 'Automatique';
$string['link_report'] = 'Rapport d’utilisation';
$string['link_test'] = 'Tester la connexion';
$string['link_tools_desc'] = '<a href="{$a->test}">Tester la connexion</a> · <a href="{$a->report}">Rapport d’utilisation</a>';
$string['needdoc'] = 'Joignez d’abord un PDF ou un fichier Word, ensuite je pourrai {$a}.';
$string['notconfigured_admin'] = 'L’assistant n’est pas encore configuré : les utilisateurs ne voient que cet avis à la place du chat. Résumés, explications, traductions, quiz, questions et audio sont indisponibles tant qu’aucun fournisseur d’IA n’est configuré. Choisissez un fournisseur et saisissez la clé API de votre compte chez lui (ou l’adresse de votre propre serveur Ollama).';
$string['notconfigured_user'] = 'L’assistant n’est pas encore disponible. Veuillez contacter votre administrateur.';
$string['opensettings'] = 'Ouvrir les paramètres';
$string['pluginname'] = 'Assistant PDF IA';
$string['privacy:metadata:audio'] = 'Fichiers audio générés pour les réponses lues';
$string['privacy:metadata:cache'] = 'Réponses d’IA en cache (résumés, explications, traductions, quiz) générées à partir du document d’un utilisateur';
$string['privacy:metadata:cache:action'] = 'L’action ayant produit la réponse';
$string['privacy:metadata:cache:response'] = 'Le texte de la réponse générée';
$string['privacy:metadata:cache:timecreated'] = 'La date de génération de la réponse';
$string['privacy:metadata:cache:userid'] = 'L’utilisateur ayant demandé la réponse';
$string['privacy:metadata:docs'] = 'Texte extrait des fichiers PDF ou Word joints par un utilisateur';
$string['privacy:metadata:docs:courseid'] = 'Le cours où le bloc a été utilisé';
$string['privacy:metadata:docs:filename'] = 'Le nom du fichier';
$string['privacy:metadata:docs:textcontent'] = 'Le texte extrait du PDF';
$string['privacy:metadata:docs:timecreated'] = 'La date d’ajout du document';
$string['privacy:metadata:docs:userid'] = 'Le propriétaire du document';
$string['privacy:metadata:externalai'] = 'Le texte du PDF et les messages de l’utilisateur sont envoyés au fournisseur d’IA choisi par l’administrateur pour produire les réponses';
$string['privacy:metadata:externalai:text'] = 'Le texte du document et la conversation';
$string['privacy:metadata:externalvoice'] = 'Le texte des réponses est envoyé au fournisseur vocal choisi par l’administrateur pour produire l’audio';
$string['privacy:metadata:externalvoice:text'] = 'Le texte à lire';
$string['privacy:metadata:log'] = 'Journal d’utilisation pour la limitation et les rapports';
$string['privacy:metadata:log:action'] = 'L’action demandée';
$string['privacy:metadata:log:provider'] = 'Le fournisseur d’IA utilisé';
$string['privacy:metadata:log:timecreated'] = 'La date de la requête';
$string['privacy:metadata:log:userid'] = 'L’utilisateur ayant fait la requête';
$string['privacy:path:activity'] = 'Activité';
$string['privacy:path:cache'] = 'Réponses en cache';
$string['privacy:path:documents'] = 'Documents';
$string['report_action'] = 'Action';
$string['report_byaction'] = 'Par action';
$string['report_cached'] = 'Réponses enregistrées';
$string['report_errors'] = 'Erreurs';
$string['report_last30'] = '30 derniers jours';
$string['report_none'] = 'Aucune activité pour le moment.';
$string['report_provider'] = 'Fournisseur';
$string['report_recent'] = 'Derniers événements';
$string['report_requests'] = 'Requêtes';
$string['report_status'] = 'Statut';
$string['report_time'] = 'Heure';
$string['report_title'] = 'Assistant PDF IA : rapport d’utilisation';
$string['report_tokens'] = 'Jetons (entrée / sortie)';
$string['report_topusers'] = 'Utilisateurs les plus actifs';
$string['report_user'] = 'Utilisateur';
$string['setting_allowcoursepdfs'] = 'Autoriser le choix de fichiers PDF et Word du cours';
$string['setting_allowcoursepdfs_desc'] = 'Les utilisateurs ne voient que les fichiers qu’ils peuvent déjà ouvrir.';
$string['setting_apikey'] = 'Clé API';
$string['setting_apikey_desc'] = 'Stockée sur le serveur et jamais envoyée aux navigateurs. Laissez vide pour Ollama ou les serveurs sans authentification.';
$string['setting_baseurl'] = 'URL de base de l’API';
$string['setting_baseurl_desc'] = 'Facultatif. Obligatoire pour « Personnalisé ». Exemple Ollama : http://localhost:11434/v1';
$string['setting_cachedays'] = 'Conserver les réponses enregistrées (jours)';
$string['setting_cachedays_desc'] = 'Les résumés, explications et traductions identiques d’un même PDF viennent de la copie enregistrée.';
$string['setting_extracttimeout'] = 'Délai maximal de lecture d’un fichier (secondes)';
$string['setting_extracttimeout_desc'] = 'La lecture d’un document est interrompue après ce délai, afin qu’un fichier endommagé ou malveillant ne puisse pas occuper le serveur. Il couvre le programme pdftotext et le lecteur intégré.';
$string['setting_limit_day'] = 'Requêtes par utilisateur et par jour';
$string['setting_limit_day_desc'] = '0 = illimité.';
$string['setting_limit_hour'] = 'Requêtes par utilisateur et par heure';
$string['setting_limit_hour_desc'] = '0 = illimité. Les réponses enregistrées ne comptent pas.';
$string['setting_maxchunks'] = 'Nombre maximal de parties par requête';
$string['setting_maxchunks_desc'] = 'Chaque partie est un appel API ; cela plafonne le coût d’un long document.';
$string['setting_maxcontextchars'] = 'Caractères du document par requête';
$string['setting_maxcontextchars_desc'] = 'Les documents plus longs sont résumés par parties puis fusionnés. Environ 4 caractères = 1 jeton.';
$string['setting_maxdecompressedmb'] = 'Quantité maximale de données après décompression (Mo)';
$string['setting_maxdecompressedmb_desc'] = 'Protège contre les bombes de décompression : un PDF est refusé si ses flux compressés dépassent ce total une fois décompressés (lecteur intégré), ou si pdftotext produit plus de texte. Les fichiers Word sont limités de la même façon.';
$string['setting_maxdocchars'] = 'Caractères conservés par document';
$string['setting_maxdocchars_desc'] = '';
$string['setting_maxfilesize'] = 'Taille maximale du fichier (Mo)';
$string['setting_maxfilesize_desc'] = 'Également limitée par PHP et Moodle.';
$string['setting_maxtokens'] = 'Longueur maximale de la réponse (jetons)';
$string['setting_maxtokens_desc'] = 'Les modèles de raisonnement en consomment une partie pour réfléchir ; utilisez au moins 2000.';
$string['setting_model'] = 'Modèle';
$string['setting_model_desc'] = 'Identifiant du modèle tel que nommé par le fournisseur. Laissez vide pour la valeur suggérée. Les noms changent souvent, consultez la documentation du fournisseur.';
$string['setting_pdftotextpath'] = 'Chemin de pdftotext';
$string['setting_pdftotextpath_desc'] = 'pdftotext (Poppler) donne le meilleur texte. S’il est absent, le lecteur PHP intégré est utilisé. Vide = désactivé.';
$string['setting_provider'] = 'Fournisseur';
$string['setting_provider_desc'] = 'OpenAI, Claude, Gemini et DeepSeek fonctionnent avec une simple clé API. Ollama et « Personnalisé » acceptent tout serveur compatible OpenAI. Les serveurs locaux ou privés doivent d’abord être autorisés dans Administration > Général > Sécurité > Sécurité HTTP.';
$string['setting_responselang'] = 'Langue des réponses';
$string['setting_responselang_desc'] = '« Automatique » répond dans la langue de la question.';
$string['setting_retentiondays'] = 'Conserver documents et audio (jours)';
$string['setting_retentiondays_desc'] = 'Les éléments plus anciens sont supprimés par une tâche planifiée.';
$string['setting_systemextra'] = 'Instructions supplémentaires';
$string['setting_systemextra_desc'] = 'Ajoutées à chaque requête, p. ex. « Utilise le vocabulaire des étudiants de première année de biologie. »';
$string['setting_temperature'] = 'Température';
$string['setting_temperature_desc'] = 'Plus bas = plus factuel. Ignorée par les modèles qui ne la permettent pas.';
$string['setting_timeout'] = 'Délai d’attente (secondes)';
$string['setting_timeout_desc'] = '';
$string['setting_tts_apikey'] = 'Clé API vocale';
$string['setting_tts_apikey_desc'] = 'Laissez vide pour réutiliser la clé du texte si le fournisseur est le même.';
$string['setting_tts_baseurl'] = 'URL de base de l’API vocale';
$string['setting_tts_baseurl_desc'] = 'Facultatif. Obligatoire pour « Personnalisé ».';
$string['setting_tts_gender'] = 'Voix';
$string['setting_tts_gender_desc'] = 'L’administrateur choisit si les réponses sont lues par une voix masculine ou féminine.';
$string['setting_tts_maxchars'] = 'Caractères lus à voix haute (maximum)';
$string['setting_tts_maxchars_desc'] = 'Les réponses plus longues sont lues jusqu’à cette limite pour maîtriser le coût.';
$string['setting_tts_model'] = 'Modèle vocal';
$string['setting_tts_model_desc'] = 'Vide = valeur par défaut.';
$string['setting_tts_provider'] = 'Fournisseur vocal';
$string['setting_tts_provider_desc'] = 'Claude et DeepSeek n’ont pas d’API vocale : associez-les ici à OpenAI, Gemini ou ElevenLabs. La voix du navigateur est gratuite mais sa qualité et le choix homme/femme dépendent de l’appareil.';
$string['setting_tts_voice_female'] = 'Identifiant de la voix féminine';
$string['setting_tts_voice_female_desc'] = 'Vide = valeur par défaut.';
$string['setting_tts_voice_male'] = 'Identifiant de la voix masculine';
$string['setting_tts_voice_male_desc'] = 'Vide = valeur par défaut.';
$string['settings_behaviour'] = 'Comportement et limites';
$string['settings_docs'] = 'Documents';
$string['settings_gettingstarted'] = 'Avant de commencer : comptes chez les fournisseurs d’IA';
$string['settings_gettingstarted_desc'] = '<p>Ce plugin n’inclut aucune IA. Il envoie le texte du document joint par l’utilisateur, et ses questions, à un service d’IA que <strong>vous</strong> choisissez et payez. Il vous faut un compte chez ce fournisseur et une clé API :</p><ul><li><strong>OpenAI</strong> : créez un compte sur platform.openai.com, ajoutez du crédit puis créez une clé API. Un abonnement ChatGPT n’inclut pas l’accès à l’API.</li><li><strong>Claude (Anthropic)</strong> : créez un compte sur console.anthropic.com, ajoutez du crédit et créez une clé API.</li><li><strong>Google Gemini</strong> : créez une clé sur aistudio.google.com/apikey. Un niveau gratuit limité existe, l’usage payant est facturé par Google.</li><li><strong>DeepSeek</strong> : créez un compte sur platform.deepseek.com, ajoutez du crédit et créez une clé API.</li><li><strong>Ollama ou tout serveur compatible OpenAI</strong> : aucun compte n’est nécessaire. Lancez le serveur vous-même (voir ollama.com), saisissez son adresse comme URL de base et autorisez cet hôte dans Administration du site &gt; Général &gt; Sécurité &gt; Sécurité HTTP s’il est sur localhost ou un réseau privé.</li></ul><p><strong>Tant qu’aucun fournisseur n’est configuré</strong>, le bloc affiche un avis à la place du chat : rien ne peut être résumé, expliqué, traduit, évalué ni répondu, et rien n’est envoyé à un fournisseur. Le bouton Écouter n’apparaît qu’une fois une voix choisie ci-dessous (la voix gratuite du navigateur ne demande aucun compte). Les fournisseurs facturent à l’usage : utilisez les limites horaires et quotidiennes de la section Comportement pour maîtriser le coût, et mentionnez le fournisseur choisi dans votre politique de confidentialité, car le texte des documents lui est envoyé.</p>';
$string['settings_text'] = 'Fournisseur d’IA pour le texte';
$string['settings_text_desc'] = 'Choisissez le service d’IA qui rédige résumés, explications, traductions et réponses. Tout passe par son API.';
$string['settings_tools'] = 'Outils';
$string['settings_voice'] = 'Voix (synthèse vocale)';
$string['settings_voice_desc'] = 'Facultatif. Permet d’écouter les réponses. Sans voix, le bouton Écouter est masqué. Choisissez une API vocale pour une qualité studio (compte et clé API chez le fournisseur nécessaires) ou la voix gratuite du navigateur, qui ne demande aucun compte.';
$string['speaklast'] = 'Voici la version audio de ma dernière réponse.';
$string['task_cleanup'] = 'Nettoyer les données de l’Assistant PDF IA';
$string['test_failed'] = 'Échec';
$string['test_latency'] = 'Temps de réponse : {$a} s';
$string['test_notconfigured'] = 'Le fournisseur de texte n’est pas encore configuré.';
$string['test_ok'] = 'Succès';
$string['test_pdf_heading'] = 'Lecture des PDF et Word';
$string['test_pdftotext_missing'] = 'pdftotext est introuvable ; le lecteur PHP intégré sera utilisé (PDF textuels uniquement).';
$string['test_pdftotext_ok'] = 'pdftotext est disponible.';
$string['test_prompt'] = 'Réponds par une courte phrase en arabe, français et anglais disant que la connexion fonctionne.';
$string['test_provider'] = 'Fournisseur : {$a}';
$string['test_run_text'] = 'Lancer le test texte';
$string['test_run_voice'] = 'Lancer le test vocal';
$string['test_text_heading'] = 'Fournisseur de texte';
$string['test_title'] = 'Assistant PDF IA : test de connexion';
$string['test_voice_browser'] = 'La voix utilise le navigateur, rien à tester côté serveur.';
$string['test_voice_disabled'] = 'La voix est désactivée.';
$string['test_voice_heading'] = 'Fournisseur vocal';
$string['test_voice_sample'] = 'Hello! This is a test of the study assistant voice. مرحبا، هذا اختبار صوت مساعد الدراسة.';
$string['tts_browser'] = 'Voix du navigateur (gratuite)';
$string['tts_custom'] = 'Personnalisé (compatible OpenAI)';
$string['tts_elevenlabs'] = 'ElevenLabs';
$string['tts_gemini'] = 'Google Gemini';
$string['tts_none'] = 'Désactivée';
$string['tts_openai'] = 'OpenAI';
