<?php
function rainyunrgs_MetaData()
{
	return ["DisplayName" => "RainyunRgs", "APIVersion" => "2.4", "HelpDoc" => "https://forum.rainyun.com/t/topic/5552"];
}
function rainyunrgs_ConfigOptions()
{
	return [
		["type" => "text", "name" => "cpu", "description" => "*CPU核心", "key" => "cpu"],
		["type" => "text", "name" => "memory", "description" => "*内存", "key" => "memory"],
		["type" => "text", "name" => "net_out", "description" => "*上行带宽", "key" => "net_out"],
		["type" => "text", "name" => "base_disk", "description" => "*系统盘", "key" => "base_disk"],
		["type" => "text", "name" => "data_disk", "description" => "*数据盘", "key" => "data_disk"],
		["type" => "text", "name" => "os_id", "description" => "*系统镜像ID", "key" => "os_id"],
		["type" => "text", "name" => "egg_type_id", "description" => "游戏类型ID", "key" => "egg_type_id"],
		["type" => "text", "name" => "subtype", "description" => "*服务器类型(VPS是kvm,雨云面板是k8s_panel)", "default" => "kvm", "key" => "subtype"],
		["type" => "text", "name" => "plan_id", "description" => "*计费套餐(plan_id,在雨云购买页显示)", "key" => "plan_id"],
		["type" => "text", "name" => "with_eip_num", "description" => "独立ip数量", "default" => "0", "key" => "with_eip_num"],
		["type" => "text", "name" => "allocation", "description" => "对外端口数", "default" => "5", "key" => "allocation"],
		["type" => "text", "name" => "online_mode", "description" => "是否为在线模式", "default" => "true", "key" => "online_mode"],
	];
}

// 图表信息
function rainyunrgs_Chart(){
	return [
		'cpu'=>[
			'title'=>'CPU 占用',
		],
		'ram'=>[
			'title'=>'剩余内存',
		],
		'disk'=>[
			'title'=>'磁盘读写',
			'select'=>[
				[
					'name'=>'系统盘',
					'value'=>'vda'
				],
			]
		],
		'flow'=>[
			'title'=>'网络流量'
		],
	];
}

// 图表数据
function rainyunrgs_ChartData($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if(empty($vserverid)){ return ['status'=>'error', 'msg'=>'数据获取失败']; }
	
	// 请求数据
	$start = $_GET["start"]/1000 ? $_GET["start"]/1000 : strtotime('-7 days');
	$end = $_GET["end"]/1000 ? $_GET["end"]/1000 : time();
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/monitor/?start_date=".$start."&end_date=".$end;
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);

	// var_dump($res);
	if($res['code'] == 200){

		$result['status'] = 'success';
		$result['data'] = [];

		$data = $res["data"]["Values"];
		$timeIndex = array_search("time", $res["data"]["Columns"]);
		usort($data, function($a, $b) use ($timeIndex) { return $a[$timeIndex] - $b[$timeIndex]; });

		// 获取cpu数据 - 我cpu烧了
		if($params['chart']['type'] == 'cpu') {
			// 给前端传数据单位
			$result['data']['unit'] = '%';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['CPU使用率(%)'];
			// 获取数据
			$cpuIndex = array_search("cpu", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$cpuIndex]*1000)/10
				]; 
			}
		}

		// 获取硬盘io数据
		if($params['chart']['type'] == 'disk') {
			// 给前端传数据单位
			$result['data']['unit'] = 'kb/s';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['读取 (kb/s)','写入 (kb/s)'];
			// 获取数据
			$writeIndex = array_search("diskwrite", $res["data"]["Columns"]);
			$readIndex = array_search("diskread", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$readIndex]*100/1024)/100
				]; 
				$result['data']['list'][1][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$writeIndex]*100/1024)/100
				]; 
			}
		}

		// 获取流量数据
		if($params['chart']['type'] == 'flow') {
			// 给前端传数据单位
			$result['data']['unit'] = 'KB/s';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['进(KB/s)','出(KB/s)'];
			// 获取数据
			$inIndex = array_search("netin", $res["data"]["Columns"]);
			$outIndex = array_search("netout", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round(($dataInfo[$inIndex]*100-1)/1024)/100
				]; 
				$result['data']['list'][1][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round(($dataInfo[$outIndex]*100-1)/1024)/100
				]; 
			}
		}

		// 获取内存数据
		if($params['chart']['type'] == 'ram') {
			// 给前端传数据单位
			$result['data']['unit'] = 'GB';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['剩余内存 (GB)'];
			// 获取数据
			$memIndex = array_search("freemem", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				if($dataInfo[$memIndex]) $result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$memIndex]*100/1024/1024/1024)/100
				];
			}
		}

		return $result;
	}else{
		return ['status'=>'error', 'msg'=>'数据获取失败'];
	}
}


function rainyunrgs_TestLink($params)
{	
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/user/";
	$res = rainyunrgs_Curl($url, null, 10, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$result["status"] = 200;
		$result["data"]["server_status"] = 1;
	} else {
		$result["status"] = 200;
		$result["data"]["server_status"] = 0;
		$result["data"]["msg"] = "未知错误";
	}
	return $result;
}
function rainyunrgs_ClientArea($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
    $url = $params["server_host"] . "/product/rgs/" . $vserverid;
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if ($res["data"]["Data"]["Plan"]["subtype"] == "k8s_panel"){
		$panel["Terminal"] = ["name" => "终端"];
		$panel["FileManage"] = ["name" => "文件管理"];
		$panel["GameSettings"] = ["name" => "游戏设置"];
	}
	if ($res["data"]["Data"]["MainIPv4"] == "-" || empty($res["data"]["Data"]["MainIPv4"])) {
		$panel["NAT"] = ["name" => "NAT转发"];
	}

	return $panel;
}
function rainyunrgs_ClientAreaOutput($params, $key)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$detail_url = $params["server_host"] . "/product/rgs/" . $vserverid;
	$res = rainyunrgs_Curl($detail_url, [], 10, "GET", $header);
	if ($key == "NAT") {
		return ["template" => "templates/NAT.html", "vars" => ["list" => $res["data"]["NatList"], "ip" => $res["data"]["Data"]["NatPublicIP"],"domain"=>$res["data"]["Data"]["NatPublicDomain"]]];
	}else if($key == "Terminal") {
		return ["template" => "templates/Terminal.html", "vars" => ["k8s_panel_helper_domain" => $res["data"]["Data"]["k8s_panel_helper_domain"], "k8s_panel_helper_token" => $res["data"]["Data"]["k8s_panel_helper_token"], "k8s_panel_namespace" => $res["data"]["Data"]["k8s_panel_namespace"]]];
	}else if($key == "FileManage") {
		return ["template" => "templates/FileManage.html", "vars" => ["k8s_panel_helper_domain" => $res["data"]["Data"]["k8s_panel_helper_domain"], "k8s_panel_helper_token" => $res["data"]["Data"]["k8s_panel_helper_token"], "k8s_panel_namespace" => $res["data"]["Data"]["k8s_panel_namespace"]]];
	}else if($key == "GameSettings") {
		return ["template" => "templates/GameSettings.html", "vars" => ["EggType"=>$res["data"]["Data"]["EggType"],"k8s_panel_start_command" => $res["data"]["Data"]["k8s_panel_start_command"], "k8s_panel_sftp_domain" => $res["data"]["Data"]["k8s_panel_sftp_domain"], "k8s_panel_sftp_setting" => $res["data"]["Data"]["k8s_panel_sftp_setting"], "k8s_panel_database_host"=> $res["data"]["Data"]["k8s_panel_database_host"], "k8s_panel_database_setting"=> $res["data"]["Data"]["k8s_panel_database_setting"]]];
	}
}
function rainyunrgs_AllowFunction()
{
	return ["client" => ["CreateSnap", "DeleteSnap", "RestoreSnap", "CreateBackup", "DeleteBackup", "RestoreBackup", "CreateSecurityGroup", "DeleteSecurityGroup", "ApplySecurityGroup", "ShowSecurityGroupAcl", "CreateSecurityGroupAcl", "DeleteSecurityGroupAcl", "MountCdRom", "UnmountCdRom", "addNatAcl", "delNatAcl", "addNatWeb", "delNatWeb", "addNat", "delNat", "ssh", "xtermjs", "SetStartCommand", "StartSFTP", "CloseSFTP", "StartMySQL", "CloseMySQL", "egg", "ChangeEgg"]
	,
	"admin"=>["xtermjs"]];
}
function rainyunrgs_egg($params){
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/egg";
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$data["egg"] = [];
		foreach($res["data"] as $egg) {
			$egg["icon_url"] = str_replace('https://rainyun-public.cn-nb1.rains3.com/assets/rgs-', 'https://ft.mhjz1.cn/assets/mgs-', $egg["icon_url"]);
			$data["egg"][] = $egg;
		}
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "获取失败"];
	}
	$url = $params["server_host"] . "/product/rgs/egg_type";
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$data["egg_type"] = [];
		foreach($res["data"] as $egg_type) {
			$egg_type["egg"]["icon_url"] = str_replace('https://rainyun-public.cn-nb1.rains3.com/assets/rgs-', 'https://ft.mhjz1.cn/assets/mgs-', $egg_type["egg"]["icon_url"]);
			$data["egg_type"][] = $egg_type;
		}
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "获取失败"];
	}
	$url = $params["server_host"] . "/product/rgs/egg_server";
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$data["egg_server"] = [];
		foreach($res["data"] as $egg_server) {
			$egg_server["icon_url"] = str_replace('https://rainyun-public.cn-nb1.rains3.com/assets/rgs-', 'https://ft.mhjz1.cn/assets/mgs-', $egg_server["icon_url"]);
			$data["egg_server"][] = $egg_server;
		}
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "获取失败"];
	}
	return ["status" => "success", "msg" => "获取成功", "data" => $data];
}
function rainyunrgs_StartMySQL($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/k8s-panel/database";
	$res = rainyunrgs_Curl($url, json_encode(["is_enabled"=>true,"version"=>"mysql-5.7"]), 30, "PATCH", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "启动成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "启动数据库失败"];
	}
}
function rainyunrgs_CloseMySQL($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/k8s-panel/database";
	$res = rainyunrgs_Curl($url, json_encode(["is_enabled"=>false,"version"=>"mysql-5.7"]), 30, "PATCH", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "关闭成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "关闭数据库失败"];
	}
}
function rainyunrgs_StartSFTP($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/k8s-panel/sftp";
	$password = randStr(16);
	$res = rainyunrgs_Curl($url, json_encode(["username"=>$params["domain"],"password"=>$password]), 30, "PATCH", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "启动成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "启动SFTP失败"];
	}
}
function rainyunrgs_CloseSFTP($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/k8s-panel/sftp";
	$res = rainyunrgs_Curl($url, json_encode(["username"=>"","password"=>""]), 30, "PATCH", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "启动成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "启动SFTP失败"];
	}
}
function rainyunrgs_SetStartCommand($params){
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/k8s-panel/set-start-command";
	$res = rainyunrgs_Curl($url, json_encode(["command"=>input("post.command")]), 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "设置成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "设置启动命令失败"];
	}
}
function rainyunrgs_CrackPassword($params, $new_pass)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "服务器不存在";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/reset-password";
	$post_data = "\n{\n    \"password\": \"" . $new_pass . "\"\n}\n";
	$res = rainyunrgs_Curl($url, $post_data, 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "重置密码成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "重置密码失败"];
	}
}
function rainyunrgs_addNat($params)
{
	$post = input("post.");
	$vserverid = rainyunrgs_GetServerid($params);
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/nat";
	$post_data = "\n\n{\n    \"port_in\": " . trim($post["port_in"]) . ",\n    \"port_out\": " . trim($post["port_out"]) . ",\n    \"port_type\": \"" . trim($post["port_type"]) . "\"\n}\n\n";
	$res = rainyunrgs_Curl($url, $post_data, 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$description = sprintf("NAT转发添加成功");
		$result = ["status" => "success", "msg" => $res["data"]];
	} else {
		$description = sprintf("NAT转发添加失败 - Host ID:%d", $params["hostid"]);
		$result = ["status" => "error", "msg" => $res["message"] ?: "NAT转发添加失败"];
	}
	active_logs($description, $params["uid"], 2);
	active_logs($description, $params["uid"], 2, 2);
	return $result;
}
function rainyunrgs_delNat($params)
{
	$post = input("post.");
	$vserverid = rainyunrgs_GetServerid($params);
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/nat/?nat_id=" . trim($post["nat_id"]);
	$res = rainyunrgs_Curl($url, [], 30, "DELETE", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$description = sprintf("NAT转发删除成功");
		$result = ["status" => "success", "msg" => $res["data"]];
	} else {
		$description = sprintf("NAT转发删除失败 - Host ID:%d", $params["hostid"]);
		$result = ["status" => "error", "msg" => $res["message"] ?: "NAT转发删除失败"];
	}
	active_logs($description, $params["uid"], 2);
	active_logs($description, $params["uid"], 2, 2);
	return $result;
}


function rainyunrgs_Renew($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
    if ($params["billingcycle"] == "monthly") {
        $duration = "1";
    } elseif ($params["billingcycle"] == "annually") {
        $duration = "12";
    } elseif ($params["billingcycle"] == "quarterly") {
        $duration = "3";
    } elseif ($params["billingcycle"] == "semiannually") {
        $duration = "6";
    } else {
        $duration = "1";
    }
    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/rgs/" . $vserverid . "/renew";
    $post_data = "\n\n{\n    \"duration\": " . $duration . ",\n    \"with_coupon_id\": 0\n}\n\n";
    $res = rainyunrgs_Curl($url, $post_data, 30, "POST", $header);
    if (isset($res["code"]) && $res["code"] == 200) {
        $detail_url = $params["server_host"] . "/product/rgs/" . $vserverid;
        $res1 = rainyunrgs_Curl($detail_url, [], 10, "GET", $header);
        $str = $res1["Data"]["ExpDate"];
        $str1 = $res1["data"]["Data"]["MonthPrice"];
        $str2 = date("Y-m-d H:i:s", $res1["data"]["Data"]["ExpDate"]);
        $log = [
            "status" => "success",
            "message" => $str2,
            "timestamp" => time(),
            "vserver_id" => $vserverid,
            "billing_cycle" => $params["billingcycle"],
            "renew_duration" => $duration
        ];
        return $log;
    } else {
        $log = [
            "status" => "error",
            "message" => $res["message"],
            "timestamp" => date("Y-m-d H:i:s", time()),
            "vserver_id" => $vserverid,
            "billing_cycle" => $params["billingcycle"],
            "renew_duration" => $duration
        ];
        return $log;
    }
}

function rainyunrgs_ChangeEgg($params){
    $vserverid = rainyunrgs_GetServerid($params);
    if (empty($vserverid)) {
        return "产品参数错误";
    }
	$post = input("post.");
    if (empty($post["egg_type_id"])) {
        return "请选择游戏版本";
    }
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/change-egg";
	$post_data = [
		"egg_type_id"=> (int)$post["egg_type_id"],
		"save_dirs"=> $post["save_dirs"]?:[],
	];
	if(isset($post["online_mode"])){
		$post_data["online_mode"] = filter_var($post["online_mode"], FILTER_VALIDATE_BOOLEAN);
	}
	$res = rainyunrgs_Curl($url, json_encode($post_data), 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "切换游戏版本成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "切换游戏版本失败"];
	}
}
function rainyunrgs_Reinstall($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
    if (empty($vserverid)) {
        return "产品参数错误";
    }
    if (empty($params["reinstall_os"])) {
        return "操作系统错误";
    }
    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/rgs/" . $vserverid . "/changeos";
    $post_data = "\n\n{\n    \"os_id\": " . $params["reinstall_os"] . "\n}\n\n";
    $res = rainyunrgs_Curl($url, $post_data, 30, "POST", $header);
    if ($res["code"] == 200) {
        if (stripos($params["reinstall_os_name"], "win") !== false) {
            $username = "administrator";
        } else {
            $username = "root";
        }
        \think\Db::name("host")->where("id", $params["hostid"])->update(["username" => $username]);
        // 密码重置成功后，发送 GET 请求获取密码
        $password_url = $params["server_host"] . "/product/rgs/" . $vserverid . "/";
        $password_res = rainyunrgs_Curl($password_url, null, 30, "GET", $header);

        if (isset($password_res["code"]) && $password_res["code"] == 200) {
            $sys_pwd = $password_res['data']['Data']['DefaultPass']; // 获取DefaultPass项内容
            $update["password"] = cmf_encrypt($sys_pwd);
            \think\Db::name("host")->where("id", $params["hostid"])->update($update);
            return ["status" => "success", "msg" => "重装系统执行成功 请刷新界面查看新的默认密码"];
        } else {
            return ["status" => "error", "msg" => $password_res["message"] ?: "获取密码失败"];
        }
    } else {
        return ["status" => "error", "msg" => $res["message"] ?: "重装失败"];
    }
}

$config_field = ["cpu","memory","net_out","base_disk","data_disk"];


function rainyunrgs_CreateAccount($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
    if (!empty($vserverid)) {
        return "已开通,不能重复开通";
    }
	$try = false;
    if ($params["billingcycle"] == "monthly") {
        $duration = 1;
    } elseif ($params["billingcycle"] == "annually") {
        $duration = 12;
    } elseif ($params["billingcycle"] == "quarterly") {
        $duration = 3;
    } elseif ($params["billingcycle"] == "semiannually") {
        $duration = 6;
    } elseif ($params["billingcycle"] == "ontrial") {
        $duration = 1;
        $try = true;
    } else {
        $duration = 1;
    }

    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/rgs/";

    $post_data = [
		"app_vars"=>[],
		"config"=>[
			"cpu"=>(int)$params["configoptions"]["cpu"],
			"memory"=>(int)$params["configoptions"]["memory"],
			"base_disk"=>(int)$params["configoptions"]["base_disk"],
			"data_disk"=>(int)$params["configoptions"]["data_disk"]?:0,
			"net_out"=>(int)$params["configoptions"]["net_out"]?:50,
		],
		"duration"=>(int)$duration,
		"pay_mode"=>"month",
		"os_id"=>(int)$params["configoptions"]["os_id"]?:0,
		"egg_type_id"=>(int)$params["configoptions"]["egg_type_id"]?:0,
		"panel_user"=>null,
		"with_eip_num"=>(int)$params["configoptions"]["with_eip_num"]?:0,
		"with_eip_flags"=>"",
		"with_eip_type"=>"",
		"cpu_limit_mode"=>false,
		"try"=>$try,
		"node_uuid"=>"",
		"online_mode"=>filter_var($params["configoptions"]["online_mode"], FILTER_VALIDATE_BOOLEAN),
	];
	if($params["configoptions"]["subtype"] == "k8s_panel"){
		$post_data["config"]["allocation"] = (int)$params["configoptions"]["allocation"];
		$post_data["config"]["database"] = 0;
		$post_data["config"]["backup"] = 0;
	}
    $res = rainyunrgs_Curl($url, json_encode($post_data), 30, "POST", $header);
    if (isset($res["code"]) && $res["code"] == 200) {
        $server_id = $res["data"]["ID"];
        $sys_pwd = $res["data"]["DefaultPass"];
        $detail_url = $params["server_host"] . "/product/rgs/" . $server_id;
        $res1 = rainyunrgs_Curl($detail_url, [], 10, "GET", $header);
        $natip = $res1["data"]["Data"]["NatPublicIP"];
        $ipv4 = $res1["data"]["Data"]["MainIPv4"];
        $customid = \think\Db::name("customfields")->where("type", "product")->where("relid", $params["productid"])->where("fieldname", "vserverid")->value("id");
        if (empty($customid)) {
            $customfields = ["type" => "product", "relid" => $params["productid"], "fieldname" => "vserverid", "fieldtype" => "text", "adminonly" => 1, "create_time" => time()];
            $customid = \think\Db::name("customfields")->insertGetId($customfields);
        }
        $exist = \think\Db::name("customfieldsvalues")->where("fieldid", $customid)->where("relid", $params["hostid"])->find();
        if (empty($exist)) {
            $data = ["fieldid" => $customid, "relid" => $params["hostid"], "value" => $server_id, "create_time" => time()];
            \think\Db::name("customfieldsvalues")->insert($data);
        } else {
            \think\Db::name("customfieldsvalues")->where("id", $exist["id"])->update(["value" => $server_id]);
        }
        $os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
        if (stripos($os_info["option_name"], "win") !== false) {
            $username = "administrator";
        } else {
            $username = "root";
        }
		$url = $params["server_host"] . "/product/rcs/" . $vserverid . "/tag";
		$res = rainyunrgs_Curl($url, json_encode(["tag_name"=>$params["domain"]]), 30, "PATCH", $header);
		// 存入IP
		$ip = [];
		if($res1["data"]["Data"]["MainIPv4"] == "-"){
		    $update["dedicatedip"] = $res1["data"]["Data"]["NatPublicIP"];
		    foreach(array_reverse($res1['data']['NatList']) as $h){
		        if($h['PortIn']==22){
		            $update['port'] = $h['PortOut'];
		        }
		    }
		}else{
		    foreach($res1['data']['EIPList'] as $v){
		        if($res1["data"]["Data"]["MainIPv4"] === $v['IP']){
		            $update["dedicatedip"] = $res1["data"]["Data"]["MainIPv4"];
		        }else{
		            $ip[] = $v['IP'];
		        }
		    }
		   $update['port'] = 0;
		}
		$update['assignedips'] = implode(',', $ip);
        $update["domainstatus"] = "Active";
        $update["username"] = $username;
        $update["domain"] = $params["domain"];
        $update["password"] = cmf_encrypt($sys_pwd);
        $update['nextduedate'] = $res1["data"]["Data"]['ExpDate'];
        if (empty($os_info)) {
            $update["os"] = $params["configoptions"]["os_id"];
        }
        \think\Db::name("host")->where("id", $params["hostid"])->update($update);
        return "ok";
    } else {
        return ["status" => "error", "msg" => "开通失败，原因：" . $res["message"]];
    }
}

function rainyunrgs_Status($params)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$detail_url = $params["server_host"] . "/product/rgs/" . $vserverid;
	$res = rainyunrgs_Curl($detail_url, [], 10, "GET", $header);
	$result["status"] = "success";
	if (isset($res["code"]) && $res["code"] == 200) {
		if ($res["data"]["Data"]["Status"] == "running") {
			$result["data"]["status"] = "on";
			$result["data"]["des"] = "运行中";
		} elseif ($res["data"]["Data"]["Status"] == "stopped") {
			$result["data"]["status"] = "off";
			$result["data"]["des"] = "已停止";
		} elseif ($res["data"]["Data"]["Status"] == "creating") {
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "创建中";
		} elseif ($res["data"]["Data"]["Status"] == "stopping") {
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "正在停止";
		} elseif ($res["data"]["Data"]["Status"] == "booting") {
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "正在操作";
		} elseif ($res["data"]["Data"]["Status"] == "banned") {
			$result["data"]["status"] = "off";
			$result["data"]["des"] = "因违规已禁封";
		}
	}else{
		$result["data"]["status"] = "unknown";
		$result["data"]["des"] = "未知";
	}
	return $result;
}

function rainyunrgs_Sync($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
	if(empty($vserverid)){
		return '产品参数错误';
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid;
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if(isset($res['code']) && $res['code'] == 200){
		// 存入IP
		$ip = [];
		if($res["data"]["Data"]["MainIPv4"] == "-"){
		    $update["dedicatedip"] = $res["data"]["Data"]["NatPublicIP"];
		    foreach(array_reverse($res['data']['NatList']) as $h){
		        if($h['PortIn']==22){
		            $update['port'] = $h['PortOut'];
		        }
		    }
		}else{
		    foreach($res['data']['EIPList'] as $v){
		        if($res["data"]["Data"]["MainIPv4"] === $v['IP']){
		            $update["dedicatedip"] = $res["data"]["Data"]["MainIPv4"];
		        }else{
		            $ip[] = $v['IP'];
		        }
		    }
		   $update['port'] = 0;
		}
		$update['assignedips'] = implode(',', $ip);
		$update['password'] = cmf_encrypt($res['data']['Data']['DefaultPass']);
		$update['domain'] = $params["domain"];
		$update['nextduedate'] = $res['data']['Data']['ExpDate'];
  		$os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
        if (stripos($os_info["option_name"], "win") !== false) {
            $update['username'] = "administrator";
        } else {
            $update['username'] = "root";
        }
		Db::name('host')->where('id', $params['hostid'])->update($update);
		return ['status'=>'success', 'msg'=>$res['message']];
	}else{
		return ['status'=>'error', 'msg'=>$res['message'] ?: '同步失败'];
	}
}

function rainyunrgs_On($params)
{
    $vserverid = rainyunrgs_GetServerid($params);
    if (empty($vserverid)) {
        return "产品参数错误";
    }

    // 获取服务器当前状态
    $status = rainyunrgs_Status($params, $vserverid);
    if ($status["data"]["status"] == "on") {
        return "开机失败，当前已经是开机状态";
    }

    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/rgs/" . $vserverid . "/start";
    $post_data = [];
    $post_data["id"] = $vserverid;
    $res = rainyunrgs_Curl($url, $post_data, 10, "POST", $header);

    if (isset($res["code"]) && $res["code"] == 200) {
        return ["status" => "success", "msg" => "开机成功"];
    } else {
        $errorMessage = isset($res["message"]) ? $res["message"] : "";
        if (strpos($errorMessage, "此产品已过期") !== false) {
            return ["status" => "error", "msg" => "开机失败，请联系工单处理"];
        } else {
            return ["status" => "error", "msg" => "开机失败，原因：" . $errorMessage];
        }
    }
}

function rainyunrgs_Off($params)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/stop";
	$post_data = [];
	$post_data["id"] = $vserverid;
	$res = rainyunrgs_Curl($url, $post_data, 10, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "关机成功"];
	} else {
		return ["status" => "error", "msg" => "关机失败，原因：" . $res["message"]];
	}
}
function rainyunrgs_Reboot($params)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if(empty($vserverid)){
        $vserverid = intval($params['old_configoptions']['customfields']['vserverid']);
        if (empty($vserverid)){
            return '产品参数错误';
        }
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/reboot";
	$post_data = [];
	$post_data["id"] = $vserverid;
	$res = rainyunrgs_Curl($url, $post_data, 10, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "重启成功"];
	} else {
		return ["status" => "error", "msg" => "重启失败，原因：" . $res["message"]];
	}
}

function rainyunrgs_ChangePackage($params)
{
	$vserverid = rainyunrgs_GetServerid($params);
	if(empty($vserverid)){
        $vserverid = intval($params['old_configoptions']['customfields']['vserverid']);
        if (empty($vserverid)){
            return '产品参数错误';
        }
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	if(isset($params['configoptions_upgrade']['with_eip_num'])){
		$ip_num = $params['configoptions']['with_eip_num'];
		$old_ip_num = $params['old_configoptions']['with_eip_num'];
		if($ip_num > $old_ip_num){
		    $url = $params["server_host"] . "/product/rgs/" . $vserverid . "/eip/";
		    $post_data = json_encode(["with_ip_num"=>intval($ip_num - $old_ip_num)]);
		    $res = rainyunrgs_Curl($url, $post_data, 10, "POST", $header);
		}
	}
	// $old_plan_id = $params['old_configoptions']['plan_id'];
	$plan_id = $params['configoptions']['plan_id'];
	$dest_config = [];
	// 具体配置项
	foreach($config_field as $k) {
		$dest_config[$k] = $params["configoptions"][$k];
	}

	$url2 = $params["server_host"] . "/product/rgs/" . $vserverid . "/scale";
	$post_data2 = json_encode(["dest_plan"=>intval($plan_id),"dest_config"=>$dest_config,"with_coupon_id"=>0]);
	
	$res = rainyunrgs_Curl($url2, $post_data2, 10, "POST", $header);

	rainyunrgs_Sync($params);
	$result['status'] = 'success';
	$result['msg'] = $res['message'] ?: '升级成功';
	return $result;
}

// VNC部分
function rainyunrgs_Vnc($params){
    $vserverid = rainyunrgs_GetServerid($params);
	$urlcs =  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
	$urlcs .= "://" . $_SERVER['HTTP_HOST'];
	if($params["configoptions"]["subtype"] == "k8s_panel"){
		$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
		$detail_url = $params["server_host"] . "/product/rgs/" . $vserverid;
		$res = rainyunrgs_Curl($detail_url, [], 10, "GET", $header);
		$url = "wss://".$res["data"]["Data"]["k8s_panel_helper_domain"]."/".$res["data"]["Data"]["k8s_panel_namespace"]."/terminal?app_install_name=".$res["data"]["Data"]["k8s_panel_namespace"]."&method=attach";
		return ["status" => "success", "url" => "$urlcs/plugins/servers/rainyunrgs/handlers/terminal.php?url=".rawurlencode(base64_encode($url))."&token=".rawurlencode($res["data"]["Data"]["k8s_panel_helper_token"]), "pass" => "YanJi-1116"];
	}
	$url = $params["server_host"] . "/product/rgs/" . $vserverid . "/vnc/?console_type=" . ( $params['rainyunrgs_console'] == "xtermjs" ? "xtermjs": "novnc" );
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrgs_Curl($url, null, 30, "GET", $header);
	if ($res["code"] != 200){
	    return ["status" => "error", "msg" => "连接 VNC 请求失败，请稍后再试"];
	}
	$data = $res["data"];
	if(empty($data['VNCProxyURL'])){
	    return ["status" => "success", "url" => "$urlcs/plugins/servers/rainyunrgs/handlers/vncRedirect.php?RequestURL=".rawurlencode($data["RequestURL"])."&RedirectURL=".rawurlencode($data["RedirectURL"])."&PVEAuth=".rawurlencode($data["PVEAuth"]), "pass" => "YanJi-1116"];
	}else{
	    return ["status" => "success", "url" => $data["VNCProxyURL"], "pass" => "YanJi-1116"];
	}
}

function rainyunrgs_xtermjs($params){
    $post = input('post.');
    $params['rainyunrgs_console'] = $post['func'];
    $vnc = rainyunrgs_Vnc($params);
    if($vnc['status']==="success"){
        return ["status" => "success", "msg" => "VNC启动成功<script type='text/javascript'>window.open('$vnc[url]', '_blank');</script>"];
    }
}

function rainyunrgs_ClientButton($params){
    $os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
    if (!stripos($os_info["option_name"], "win") && $params["configoptions"]["subtype"] == "kvm") {
         $button = [
                   'xtermjs'=>[
                            'place'=>'console',   // 支持control和console 分别输出在控制和控制台
                            'name'=>'Xtermjs'     // 按钮名称
                   ],
                   'ssh'=>[
                            'place'=>'console',   // 支持control和console 分别输出在控制和控制台
                            'name'=>'SSH'     // 按钮名称
                   ],
         ];
         return $button;
    }
}

function rainyunrgs_ssh($params){
    $url="https://ssh.mhjz1.cn/?hostname=".$params['dedicatedip']."&username=".$params['username']."&password=".base64_encode($params['password']);
    return ["status" => "success", "msg" => "SSH启动成功<script type='text/javascript'>window.open('$url', '_blank');</script>"];
}


function rainyunrgs_FiveMinuteCron() {
	$serverRows = \think\Db::name('servers')  
	            ->where('type', 'rainyunrgs')  
	            ->field('hostname, password, gid')  
	            ->select();
	$result = [];
	foreach ($serverRows as $serverRow) {
		$productRows = \think\Db::name('products')  
		                ->where('server_group', $serverRow['gid'])  
		                ->field('id, config_option3')  
		                ->select();
		if (!empty($productRows)) {
			$result[] = [  
			                    'server' => [  
			                        'host' => $serverRow['hostname'],  
			                        'password' => $serverRow['password'],
			                    ],  
			                    'products' => $productRows,  
			                ];
		}
	}
	foreach ($result as $item) {
		$server = $item['server'];
		$host = $server['host'];
		$password = $server['password'];
		$gid = $server['gid'];
		foreach ($item['products'] as $product) {
			$id = $product['id'];
			$pid = $product['config_option3'];
			$url = $host . "/product/rgs/plans";
			$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $password];
			$res = rainyunrgs_Curl($url, null, 30, "GET", $header)['data'];
			foreach ($res as $product) {
				if ($product['id'] == $pid) {
					$availableStock = $product['available_stock'];
					$cpu = $product['cpu'];
					$memory = $product['memory'];
					$net_in = $product['net_in'];
					$net_out = $product['net_out'];
					$region = $product['region'];
					break;
				}
			}
			\think\Db::name("products")->where("id", $id)->update(["qty" => $availableStock]);
		}
	}
}

function rainyunrgs_GetServerid($params)
{
	return $params["customfields"]["vserverid"];
}
function rainyunrgs_Curl($url = "", $data = [], $timeout = 30, $request = "POST", $header = [])
{
	$curl = curl_init();
	if ($request == "GET") {
		$s = "";
		if (!empty($data)) {
			foreach ($data as $k => $v) {
				$s .= $k . "=" . urlencode($v) . "&";
			}
		}
		if ($s) {
			$s = "?" . trim($s, "&");
		}
		curl_setopt($curl, CURLOPT_URL, $url . $s);
	} else {
		curl_setopt($curl, CURLOPT_URL, $url);
	}
	curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($curl, CURLOPT_USERAGENT, "Mofang");
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	if (strtoupper($request) == "GET") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPGET, 1);
	}
	if (strtoupper($request) == "POST") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		if (is_array($data)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		} else {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
	}
	if (strtoupper($request) == "PUT" || strtoupper($request) == "DELETE" || strtoupper($request) == "PATCH") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($request));
		if (is_array($data)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		} else {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
	}
	if (!empty($header)) {
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	}
	$res = curl_exec($curl);
	$error = curl_error($curl);
	if (!empty($error)) {
		return ["status" => 500, "message" => "CURL ERROR:" . $error];
	}
	$info = curl_getinfo($curl);
	curl_close($curl);
	return json_decode($res, true);
}