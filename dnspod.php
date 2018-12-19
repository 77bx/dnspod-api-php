<?php
if(empty($_GET['sub_domain'])){
	showmsg('域名解析名不能为空','sub_domain');
}elseif(empty($_GET['domain'])){
	showmsg('域名不能为空','domain');
}elseif(empty($_GET['id'])){
	showmsg('DNSPOD API Token ID不能为空','id');	
}elseif(empty($_GET['token'])){
	showmsg('DNSPOD API Token值不能为空','token');			
}elseif(empty($_GET['ip'])){
	showmsg('没有新的IP地址','ip');	
}else{
	$key = addslashes($_GET['id']).','.addslashes($_GET['token']);
	$domain = addslashes($_GET['domain']);
	$sub_domain = addslashes($_GET['sub_domain']);
	$domain_all = $sub_domain.'.'.$domain;
	$ip = addslashes($_GET['ip']);
	$type = empty($_GET['type'])?'A':addslashes($_GET['type']);
	$line = empty($_GET['line'])?'default':addslashes($_GET['line']);
}

//line参数设置
$line_array = array('default'=>'默认','ctc'=>'电信','cucc'=>'联通','cernet'=>'教育网','cmcc'=>'移动','ctt'=>'铁通','home'=>'国内','abord'=>'国外','search'=>'搜索引擎','baidu'=>'百度','google'=>'谷歌','youdao'=>'有道','bing'=>'必应','soso'=>'搜搜','sogou'=>'搜狗','qihu'=>'奇虎');
if(array_key_exists($line, $line_array)){
	$line = $line_array[$line];
}else{
	showmsg('请输入正确的line参数，line为空为默认线路','line');
}

//获取域名id
//fix：通过域名报错问题
$list_url = 'https://dnsapi.cn/Domain.List';
$list_token = array ("login_token" => $key,"format" => "json");
$list_data = json_decode(ssl_post($list_token,$list_url), true);
if($list_data['status']['code']!=1){
	showmsg('请输入正确的DNSPOD API token的id和值','id & token');
}
foreach($list_data['domains'] as $value){
	if($value['name'] == $domain){
		$domain_id = $value['id'];
		break;
	}
}
if(empty($domain_id)){
	showmsg('该账号下未找到域名'.$domain,'domain');
}

//获取域名下记录列表
$record_url =  'https://dnsapi.cn/Record.List';
$record_token = array ("login_token" => $key,"format" => "json","domain_id" =>$domain_id);
$record_data = json_decode(ssl_post($record_token,$record_url), true);
foreach($record_data['records'] as $value){
	if($value['name'] == $sub_domain && $value['type']==$type && $value['line'] == $line){
		$record_id = $value['id'];
		$record_value = $value['value'];
		break;
	}
}
if(empty($record_id)){
	showmsg('未找到解析名为'.$sub_domain.'记录，请检查是否为记录类型和线路','type & line');
}
if($record_value == $ip){
	showmsg('相同的IP地址，由DNSPOD API得到，无法跳过','IP');
}

//修改记录值
$dns_url = 'https://dnsapi.cn/Record.Modify';
$dns_token = array ("login_token" => $key,"format" => "json","domain" => $domain,"record_id" => $record_id,"sub_domain" => $sub_domain,"record_line" => $line,"value" => $ip,"record_type" => $type);
$dns_data = json_decode(ssl_post($dns_token,$dns_url), true);
if($dns_data['status']['code']==1){
	showmsg('DNS记录更新成功，当前'.$domain_all.'解析IP为'.$ip,'','green');
}else{
	showmsg('DNS记录更新失败','未知,id:'.$dns_data['status']['code']);
}

function showmsg($text,$err='',$status='error'){
	echo '<html><head><title>提示信息 - DNSPOD API动态域名解析V1.3 - By StarYu</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> </head><body><h1 style="text-align:center;">DNSPOD API动态域名解析V1.3 - By StarYu<h1><h2 style="text-align:center;color:';
	echo $status == 'error'?'red">Error ':'green">Success ';
	echo $err.'：'.$text;
	echo '</h3><p style="text-align:center;">示例：http://u.myxzy.com/dnspod.php?id=12345&token=abc1234567890abc1234567890&ip=1.1.1.1&domain=myxzy.com&sub_domain=www&line=cmcc</p><p style="text-align:center;">技术支持帮助咨询请去<a href="http://www.myxzy.com/post-464.html">http://www.myxzy.com/post-464.html</a></p></body></html>';
	exit();
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