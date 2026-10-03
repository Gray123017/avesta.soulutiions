<?php
require_once __DIR__ . '/assistant-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function av_chat_json($data, $status=200) { http_response_code($status); echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
$config = av_chat_config(__DIR__);
$transport = function_exists('curl_init');
$ready = $config['key'] !== '' && $transport;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'status') {
    av_chat_json(['configured'=>$ready, 'webAvailable'=>$ready, 'version'=>'20261003-site-scope', 'reason'=>$ready ? null : ($config['key']==='' ? 'missing_key' : 'missing_curl')]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') av_chat_json(['error'=>'Use POST to ask a question.'],405);
if (($_SERVER['HTTP_X_AVESTA_CHAT'] ?? '') !== '1' || !preg_match('~^application/json(?:;|$)~i', $_SERVER['CONTENT_TYPE'] ?? '')) av_chat_json(['error'=>'Invalid chat request.'],400);
if (isset($_SERVER['HTTP_ORIGIN']) && !in_array($_SERVER['HTTP_ORIGIN'], ['https://avesta.solutions','https://www.avesta.solutions'],true)) av_chat_json(['error'=>'Invalid chat origin.'],403);
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (strlen($raw)>8192) av_chat_json(['error'=>'Please shorten your question.'],413);
$body=json_decode($raw,true); $message=is_array($body) && is_string($body['message'] ?? null) ? trim($body['message']) : '';
if (!$message || strlen($message)>4000 || ($body['web'] ?? false)!==true) av_chat_json(['error'=>'Enter a short question and choose AI and online sources.'],400);
if (preg_match('~\b\d{6}/\d{2}/\d\b|sk-[A-Za-z0-9_-]{20,}~',$message)) av_chat_json(['error'=>'Remove NRC numbers and API keys before sending.'],400);
if (!$ready) av_chat_json(['error'=>'AI setup is incomplete. Saved guidance is still available.'],503);
if (!av_chat_reserve(__DIR__.'/assistant_rate.php',$_SERVER['REMOTE_ADDR'] ?? 'unknown',time())) av_chat_json(['error'=>'AI request limit reached or temporarily unavailable. Use saved guidance or contact Avesta.'],429);
$payload=['model'=>$config['model'], 'store'=>false, 'max_output_tokens'=>800,
 'instructions'=>av_chat_instructions(__DIR__, av_chat_scope($body)),
 'input'=>$message, 'tools'=>[['type'=>'web_search','search_context_size'=>'low']], 'tool_choice'=>'auto', 'max_tool_calls'=>1];
$ch=curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>28,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$config['key']],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_FOLLOWLOCATION=>false]);
$raw=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if ($raw===false || !$status) av_chat_json(['error'=>'OpenAI could not be reached. Retry or use saved guidance.'],502);
$response=json_decode($raw,true);
if ($status<200 || $status>=300) {
    $code=$response['error']['code'] ?? '';
    $error=$status===401 ? 'OpenAI rejected the server key. Check or replace it in the private settings file.' : ($code==='insufficient_quota' ? 'The OpenAI project has no available API quota. The site owner needs to check API billing.' : ($status===429 ? 'OpenAI is limiting requests. Please retry later.' : 'OpenAI could not answer. The site owner should check API permissions and model access.'));
    av_chat_json(['error'=>$error],502);
}
$answer=av_chat_answer(is_array($response)?$response:[]);
if (!$answer) av_chat_json(['error'=>'OpenAI returned no answer. Retry or use saved guidance.'],502);
av_chat_json($answer);
