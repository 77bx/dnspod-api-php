<?php
/***
* Dnspod-api-php V1.7
* By Star.Yu
***/
if($_SERVER['REQUEST_METHOD']=="POST"){
  $request = $_POST;
}
if($_SERVER['REQUEST_METHOD']=="GET"){
  $request = $_GET;
}
if(is_array($request)&&count($request)<1){
  Header("Location: http://u.myxzy.com/dnspod/demo.php"); 
  exit; 
}
$format = empty($request['format'])?'xml':strtolower(addslashes($request['format']));
if(empty($request['token'])){
  $message = 'Token cannot be empty';
  output("0",$message);
}elseif(empty($request['domain'])){
  $message = 'Domain cannot be empty';
  output("0",$message);
}elseif(empty($request['record'])){
  $message = 'Record cannot be empty';
  output("0",$message);
}else{
  $token = addslashes($request['token']);
  $ip = empty($request['ip']) ? $_SERVER['REMOTE_ADDR'] : addslashes($request['ip']);
  $domain = addslashes($request['domain']);
  $sub_domain = addslashes($request['record']);
  $domain_all = $sub_domain.'.'.$domain; 
  $type = empty($request['type']) ? 'A' : strtoupper(addslashes($request['type']));
  $line = empty($request['line']) ? 'default' : addslashes($request['line']);
}

//判断type和ip值
if($type != 'A' && $type != 'AAAA' && $type != 'CNAME' && $type != 'MX'){
  $message = 'Type error';
  output("0",$message);  
}
if($type === 'A' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
  $message = 'IPv4 error';
  output("0",$message); 
}
if($type === 'AAAA' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
  $message = 'IPv6 error';
  output("0",$message); 
}

//line参数设置
$line_array = array('default'=>'默认','ctc'=>'电信','cucc'=>'联通','cernet'=>'教育网','cmcc'=>'移动','ctt'=>'铁通','home'=>'国内','abord'=>'国外','search'=>'搜索引擎','baidu'=>'百度','google'=>'谷歌','youdao'=>'有道','bing'=>'必应','soso'=>'搜搜','sogou'=>'搜狗','qihu'=>'奇虎');
if(array_key_exists($line, $line_array)){
	$line = $line_array[$line];
}else{
	$message = 'Line error';
  output("0",$message);  
}

//获取域名id
$list_url = 'https://dnsapi.cn/Domain.List';
$list_token = array ("login_token" => $token,"format" => "json");
$list_data = json_decode(ssl_post($list_token,$list_url), true);
if($list_data['status']['code']!=1){
  $message = 'Token error';
  output($list_data['status']['code'],$message);  
}
foreach($list_data['domains'] as $value){
	if($value['name'] == $domain){
		$domain_id = $value['id'];
		break;
	}
}
if(empty($domain_id)){
  $message = 'Domain error';
  output("0",$message);    
}

//获取域名下记录列表
$record_url =  'https://dnsapi.cn/Record.List';
$record_token = array ("login_token" => $token,"format" => "json","domain_id" =>$domain_id,"offset"=>0,"length"=>3000);
$record_data = json_decode(ssl_post($record_token,$record_url), true);
foreach($record_data['records'] as $value){
	if($value['name'] == $sub_domain && $value['type']==$type && $value['line'] == $line){
		$record_id = $value['id'];
		$record_value = $value['value'];
		break;
	}
}

//解析记录不存在即创建解析记录
if(empty($record_id)){
  $record_create_url =  'https://dnsapi.cn/Record.Create';
  $record_create_token =  array ("login_token" => $token,"format" => "json","domain_id" =>$domain_id,"sub_domain" =>$sub_domain,"record_type" => $type,"record_line" => $line,"value" => $ip,"ttl" => '120');
  $record_create_data = json_decode(ssl_post($record_create_token,$record_create_url), true);
  if($record_create_data['status']['code']==1){
    $message = 'Record created success, ip updated';
    output("1",$message);
  }else{
    $message = 'Record created error, Please add manually';
    output($record_create_data['status']['code'],$message);
  }
}

//ip相同跳过更新，防止账号被锁
if($record_value == $ip){
  $message = 'IP same, not updated';
  output("0",$message);
}

//A记录更新记录值（ttl自动变为10），AAAA/CNAME/MX修改记录值
if($type == 'A'){
  $dns_url = 'https://dnsapi.cn/Record.Ddns';
  $dns_token = array ("login_token" => $token,"format" => "json","domain_id" => $domain_id,"record_id" => $record_id,"sub_domain" => $sub_domain,"record_line" => $line,"value" => $ip);
}else{
  $dns_url = 'https://dnsapi.cn/Record.Modify';
  $dns_token = array ("login_token" => $token,"format" => "json","domain_id" => $domain_id,"record_id" => $record_id,"sub_domain" => $sub_domain,"record_line" => $line,"value" => $ip,"record_type" => $type);  
}
$dns_data = json_decode(ssl_post($dns_token,$dns_url), true);
if($dns_data['status']['code']==1){
  $message = 'Record updated success. domain:'.$domain_all.'['.$ip.']';
  output("1",$message);  
}else{
  $message = 'Record updated error.';
  output($dns_data['status']['code'],$message);  
}

function ssl_post($data,$url){
    $curl = curl_init(); 
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); 
    curl_setopt($curl, CURLOPT_POST, 1); 
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); 
	curl_setopt($curl, CURLOPT_HEADER, 0); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
    $tmpInfo = curl_exec($curl); 
    if (curl_errno($curl)) {
       echo 'Errno'.curl_error($curl);
    }
    curl_close($curl); 
    return $tmpInfo; 
}

//输出函数
function output($status,$message){
  global $format;
  $dns['code'] = $status;
  $dns['message'] = $message;
  $dns['time'] = date("Y-m-d h:i:s");
  $dns['info'] = 'dnspod-api-php V1.7 By Star.Yu';  
  if($format == 'json'){
    header('Content-Type:application/json; charset=utf-8');
    exit(json_encode($dns,true|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));    
  }else{
    header('Content-Type:application/xml; charset=utf-8');
    exit(arr2xml($dns));    
  }
}

//数组转xml
function arr2xml($data, $root = true){
    $str="";
    if($root)$str .= "<xml>";
    foreach($data as $key => $val){
        if(is_array($val)){
            $child = arr2xml($val, false);
            $str .= "<$key>$child</$key>";
        }else{
            $str.= "<$key>$val</$key>";
        }
    }
    if($root)$str .= "</xml>";
    return $str;
}