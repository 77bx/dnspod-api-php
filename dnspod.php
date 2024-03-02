<?php
/***
* Dnspod-api-php V1.9
* By Star.Yu
* 差不多是老接口的最后版本了。
***/
if($_SERVER['REQUEST_METHOD']=="POST"){
  $request = $_POST;
}
if($_SERVER['REQUEST_METHOD']=="GET"){
  $request = $_GET;
}

if(empty($request)){
  Header("Location: https://api.77bx.com/dnspod/demo.php"); 
  exit; 
}

$format = empty($request['format'])?'xml':strtolower(addslashes($request['format']));
if(empty($request['token'])){
  output(0,'Token cannot be empty');
}elseif(empty($request['domain'])){
  output(0,'Domain cannot be empty');
}elseif(empty($request['record'])&&empty($request['record_id'])){
  output(0,'Record cannot be empty');
}else{
  $token = addslashes($request['token']);
  $ip = empty($request['ip']) ? $_SERVER['REMOTE_ADDR'] : addslashes($request['ip']);
  $type = empty($request['type']) ? 'A' : strtoupper(addslashes($request['type']));
  $line = empty($request['line']) ? 'default' : addslashes($request['line']);
  $domain = addslashes($request['domain']);
  $record = empty($request['record'])?'':addslashes($request['record']);
  $record_id = empty($request['record_id'])?'':intval($request['record_id']);
}

if(stripos($ip,'/')!==false){
  $ip = substr($ip,0,stripos($ip,'/'));
}

//判断type和ip值
if($type != 'A' && $type != 'AAAA' && $type != 'CNAME' && $type != 'MX'){
  output(0,'Type error');
}
if($type === 'A' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
  output(0,'IPv4 error');
}
if($type === 'AAAA' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
  output(0,'IPv6 error');
}

//line参数设置
$line_array = array('default'=>'默认','ctc'=>'电信','cucc'=>'联通','cernet'=>'教育网','cmcc'=>'移动','ctt'=>'铁通','home'=>'国内','abord'=>'国外','search'=>'搜索引擎','baidu'=>'百度','google'=>'谷歌','youdao'=>'有道','bing'=>'必应','soso'=>'搜搜','sogou'=>'搜狗','qihu'=>'奇虎');
if(array_key_exists($line, $line_array)){
	$line = $line_array[$line];
}else{
  output(0,'Line error');
}

// 查询记录信息
if(empty($record_id)){
  $data = array ("login_token" => $token,"format" => "json","domain" =>$domain,'sub_domain'=>$record,'record_type'=>$type,'record_line'=>$line,"offset"=>0,"length"=>3000);
  $get_record_info = ssl_post($data,'https://dnsapi.cn/Record.List');
  $get_record_info['action'] = 'List';
}else{
  $data = array ("login_token" => $token,"format" => "json","domain" =>$domain,'record_id'=>$record_id);
  $get_record_info = ssl_post($data,'https://dnsapi.cn/Record.Info');
  $get_record_info['action'] = 'Info';
}
if($get_record_info['status']['code']==10){
  // 创建记录
  $data = array ("login_token" => $token,"format" => "json","domain" =>$domain,"sub_domain" =>$record,"record_type" => $type,"record_line" => $line,"value" => $ip,"ttl" => '600');
  $action_info = ssl_post($data,'https://dnsapi.cn/Record.Create');
  if($action_info['status']['code']==1){
    output(1,'Record created success, ip updated');
  }else{
    output($action_info['status']['code'],$action_info['status']['message']);
  }
}elseif($get_record_info['status']['code']==1){
  $record_info = $get_record_info['action']=='List' ? $get_record_info['records'][0] : $get_record_info['record'];
}else{
  output($get_record_info['status']['code'],$get_record_info['status']['message']);
}

// 更新
if(!empty($record_info)){
  if($record_info['value'] == $ip){
    output(1,'IP same, not updated');
  }else{
    if($record_info['type'] == 'A'){
      $data = array ("login_token" => $token,"format" => "json","domain" => $domain,"record_id" => $record_info['id'],"record_line" => $record_info['line'],"value" => $ip);
      $get_ddns_info = ssl_post($data,'https://dnsapi.cn/Record.Ddns');
    }else{
      $data = array ("login_token" => $token,"format" => "json","domain" => $domain,"record_id" => $record_info['id'],"record_line" => $record_info['line'],"value" => $ip,"record_type" => $record_info['type']);
      $get_ddns_info = ssl_post($data,'https://dnsapi.cn/Record.Modify');
    }
    if($get_ddns_info['status']['code']==1){
      output(1,'Record updated success. domain:'.$record_info['record'] .'.'. $domain.'['.$ip.']');
    }else{
      output($get_ddns_info['status']['code'],$get_ddns_info['status']['message']);
    }
  }
}
output(0,'unknow error');

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
  $info = curl_exec($curl);
  if (curl_errno($curl)) {
      echo 'Errno'.curl_error($curl);
  }
  curl_close($curl);
  return json_decode($info,true);
}

//输出函数
function output($status,$message){
  global $format;
  $dns['code'] = $status;
  $dns['message'] = $message;
  $dns['time'] = date("Y-m-d h:i:s");
  $dns['info'] = 'dnspod-api-php V1.9 By Star.Yu';
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
