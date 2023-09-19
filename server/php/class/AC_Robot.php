<?php

class AC2_Robot extends JDApiBase
{
	protected $cmds = [];

	function __construct() {
/*
示例：

	新增表，表名=[设备]，英文名=[Equipment]，(可选)菜单项=[设备台帐-设备管理]，字段列表=
	- [设备名称]，（可选，不指定则与前面相同）字段名=[name]
	- [购入日期]，字段名=[dt]
	- [地点]
	- [状态]，下拉选项=[良好 故障]（其它示例: [OK=良好 NOK=故障]）
	- [操作人]，（用于关联选择）链接字段=[Employee.name]

JSON格式：

	{
		表名: "设备",
		英文名: "Equipment",
		菜单项: "设备台帐-设备管理",
		字段列表: [
			{标题:"设备名称", 字段名: "name"},
			{标题: "购入日期"},
			{标题: "地点"},
			{标题: "状态", 下拉选项: "良好 故障"},
			{标题: "操作人", 关联表: "Employee", 关联字段: "name"}
		]
	}

若表已存在则覆盖更新（如果要做补丁更新，可使用修改字段命令）

- 关联表、关联字段: 关联到其它表。UI显示为下拉数据表。
- 下拉选项: 标准格式为"选项1;选项2"列表，或是"CR:创建;RE:完成"映射列表，也兼容"选项1 选项2"或"CR=创建 RE=完成"（第一层可用";"或中文、英文空格分隔，第二层可用":"或"="分隔）等写法。UI将显示为下拉选项。
支持特殊值"YesNoMap"它相当于"0:否;1:是"

*/
		$this->cmds["新增表"] = [
			"param" => [ "表名", "英文名", "菜单项?", "字段列表" => ["标题", "字段名?", "关联表?", "关联字段?", "下拉选项?"] ],
			// exec()成功返回: null 或 数组, 失败可用jdRet返回
			"exec" => function ($param) {
				if (count($param["字段列表"]) == 0)
					return "至少要有一个字段哦";

				require_once(__DIR__ . "/../../tool/upgrade/upglib.php"); // parseFieldDef
				$cols = array_map(function ($field) {
					return self::createCol($field);
				}, $param["字段列表"]);
				if ($cols[0]["name"] != "id") {
					array_unshift($cols, [
						"name" => "id",
						"title" => "编号",
						"type" => "i"
					]);
				}

				# @DiMeta: id, name, manageAcFlag, title, cols(t), vcols(t), subobjs(t), acLogic(t)
				$data = [
					"name" => $param["英文名"],
					"title" => $param["表名"],
					"cols" => $cols,
					"manageAcFlag" => 1
				];

				$rv = self::addTable($data);

				// 直接加菜单项
				if ($param["菜单项"]) {
					$rv1 = $this->execCmd([
						"cmd" => "新增菜单",
						"param" => [
							"菜单项" => $param["菜单项"],
							"页面" => $param["表名"]
						]
					]);
					arrCopy($rv, $rv1);
				}
				return $rv;
			}
		];

/*
示例：

	新增菜单，菜单项=[设备台帐-设备管理], 页面=[设备]

JSON格式：

	{
		菜单项: "设备台帐-设备管理",
		页面: "设备",
	}
*/
		$this->cmds["新增菜单"] = [
			"param" => [ "菜单项", "页面" ],
			"exec" => function ($param) {
				$page = $param['页面'];
				if (startsWith($page, "page")) {
					$value0 = "WUI.showPage('$page')";
				}
				else if (startsWith($page, "dlg")) {
					$value0 = "WUI.showDlg('#$page')";
				}
				else {
					$value0 = "WUI.showPage('pageUi', '$page')";
				}
				return self::addMenu($param["菜单项"], $value0);
			}
		];

/*
示例：

	修改字段，表名=[设备]，字段列表=
	- [设备图]
	- [缺陷图]
	- 删除[地点]

JSON格式：

	{
		表名: "设备",
		字段列表: [
			{title:"设备图"},
			{title: "缺陷图"},
			{title: "地点", _delete: 1}, // 表示删除
		]
	}

*/
		$this->cmds["修改字段"] = [
			"param" => [ "表名", "字段列表" => ["标题", "字段名?", "链接字段?", "下拉选项?"]],
			"exec" => function ($param) {
				# @DiMeta: id, name, manageAcFlag, title, cols(t), vcols(t), subobjs(t), acLogic(t)
				$di = callSvcInt("DiMeta.query", ["cond" => ["title"=>$param["表名"]], "fmt"=>"one"]);
				$cols = jsonDecode($di["cols"]);
				require_once(__DIR__ . "/../../tool/upgrade/upglib.php"); // parseFieldDef
				foreach ($param["字段列表"] as $field) {
					$idx = -1;
					$title = $field["标题"];
					arrFind($cols, function ($e) use ($title) {
						return $e["title"] == $title || $e["name"] == $title; 
					}, $idx);
					if ($field["_delete"]) {
						if ($idx < 0)
							jdRet(E_PARAM, null, "找不到字段: $title");
						array_splice($cols, $idx, 1);
						continue;
					}
					$col = self::createCol($field);
					if ($idx >= 0)
						$cols[$idx] = $col;
					else
						$cols[] = $col;
				}
				$di["cols"] = $cols;
				$rv = self::addTable($di);
				return $rv;
			}
		];

		// `新增子表关联，表名=[设备]，关联表名=[设备巡检记录]`,
		$this->cmds["新增子表关联"] = [
			"param" => ["表名", "关联表名"],
			"exec" => function ($param) {
				return self::addRelation($param["表名"], $param["关联表名"], true);
			}
		];
		// `新增关联，表名=[设备]，关联表名=[设备巡检记录]`,
		$this->cmds["新增关联"] = [
			"param" => ["表名", "关联表名"],
			"exec" => function ($param) {
				return self::addRelation($param["表名"], $param["关联表名"], false);
			}
		];

/*
示例：

	图像检测，表名=[设备]，源图片字段=[设备图]，输出图字段=[缺陷图]，(可选)模型=[yolov8]

JSON格式：

	{
		表名: "设备",
		源图片字段: "设备图",
		输出图字段: "缺陷图",
		模型: "yolov8"
	}

*/
		$this->cmds["图像检测"] = [
			"param" => [ "表名", "源图片字段", "输出图字段", "模型?" ],
			"exec" => function ($param) {
				# @DiMeta: id, name, manageAcFlag, title, cols(t), vcols(t), subobjs(t), acLogic(t)
				$rv = callSvcInt("DiMeta.query", ["res"=>"id", "cond" => ["title" => $param["表名"]], "fmt"=>"one?"]);
				if (!$rv)
					jdRet(E_PARAM, null, "找不到表: " . $param["表名"]);
				$diId = $rv;

				$code = <<<EOL
if (issetval("{$param['源图片字段']}")) {
	\$opt = [
		"picId" => \$_POST["{$param['源图片字段']}"],
		"model" => "{$param['模型']}"
	];
	\$rv = AiDetect::runDetect(\$opt);
	if (\$rv[0]==0 && count(\$rv[1]) > 0)
		\$_POST["{$param['输出图字段']}"] = \$opt["out"];
}
EOL;
				$acLogic = jsonEncode([
					[
						"class" => "AC0",
						"onValidate" => $code
					]
				], true);

				callSvcInt("DiMeta.set", ["id" => $diId], ["acLogic" => $acLogic]);
			}
		];

/*
示例：

	添加领域模型，领域模型名=[通用仓储WMS]

JSON格式param：

	{
		领域模型名: "通用仓储WMS"
	}

领域模型的实质就是addon, 自动加载路径为 addon/{领域模型名}.xml
注意目前系统限制只能安装一个addon。
*/
		$this->cmds["添加领域模型"] = [
			"param" => [ "领域模型名" ],
			"exec" => function ($param) {
				$name = $param["领域模型名"];
				$f = "addon/$name.xml";
				if (! file_exists($f))
					jdRet(E_PARAM, "cannot find model", "找不到领域模型: $name");
				copy($f, "tool/upgrade/addon.xml");
				$url = getBaseUrl() . "/tool/upgrade-addon.php/install?fmt=json";
				$rv = httpCall($url);
				$rv1 = jsonDecode($rv);
				if ($rv1[0] != 0)
					jdRet(E_EXT, null, "安装领域模型失败:" . $rv);
				return [
					"<a onclick=\"WUI.reloadSite()\">刷新应用</a>"
				];
			}
		];

		// "新增统计报表，表名=[设备巡检记录]，（可选）行=[巡检时间-年月 设备]，（可选）列=[巡检结果]，（可选）菜单项=[设备台账-巡检月报表]"
		$this->cmds["新增统计报表"] = [
			"param" => [ "表名", "菜单项", "行统计字段?", "列统计字段?", ],
			"exec" => function ($param) {
				$ui = callSvcInt("UiMeta.query", ["cond" => ["name"=>$param["表名"]], "fmt"=>"one"]);
				$fields = jsonDecode($ui["fields"]);
				// $resFields = "id 编号,equName 设备,equ购入日期 购入日期,时间,巡检人Name 巡检人,巡检结果 巡检结果=OK:良好;NOK:故障",
				$resFields = null;
				$f2res = function ($f) {
					if ($f["name"] == $f["title"])
						return $f["name"];
					return $f["name"] . ' ' . $f["title"];
				};
				foreach ($fields as $f) {
					addToStr($resFields, $f2res($f));
				}

				$tmField = null;
				$gres = [];
				$gres2 = [];
				$tmMap = [
					"年月" => "y 年,m 月",
					"年月日" => "y 年,m 月,d 日",
					"年季度" => "y 年,q 季度",
					"年周" => "y 年,w 周",
					"年月日时" => "y 年,m 月,d 日,h 时",
				];
				$gresList = [];
				foreach (["行统计字段", "列统计字段"] as $idx=>$g) {
					if (!$param[$g])
						continue;
					foreach (preg_split('/\s+/u', $param[$g]) as $f) {
						$tmRes = null;
						if (strpos($f, '-') > 0) {
							$a = explode('-', $f);
							$tmRes = $tmMap[$a[1]];
							if ($tmRes) {
								$f = $a[0];
							}
						}
						$f1 = arrFind($fields, function ($f1) use ($f) {
							return $f1["title"] == $f;
						});
						if (!$f1)
							jdRet(E_PARAM, "no field $f", "表[{$param["表名"]}]中没有字段[$f]");

						if ($tmRes) {
							if ($tmField)
								jdRet(E_PARAM, null, "只能指定一个时间统计字段");
							$tmField = $f2res($f1);
							$gresList[$idx][] = $tmRes;
						}
						else {
							$gresList[$idx][] = $f2res($f1);
						}
					}
				}
				$gres = $gresList[0]? jsonEncode($gresList[0]): "null";
				$gres2 = $gresList[1]? jsonEncode($gresList[1]): "null";
				$code = <<<EOL
WUI.showDataReport({
  "title": "统计报表: {$param["表名"]}",
  "ac": "{$ui["obj"]}.query",
  "res": "COUNT(1) 总数",
  "resFields": "$resFields",
  "tmField": "$tmField",
  "detailPageName": "pageUi",
  "gres": $gres,
  "gres2": $gres2,
  "showSum": 1
})
EOL;
				$rv = self::addMenu($param["菜单项"], $code);
				return $rv;
			}
		];

		// "新增审批流程，表名=[设备巡检记录]，审批角色=[经理]"
		$this->cmds["新增审批流程"] = [
			"param" => [ "表名", "审批角色" ],
			"exec" => function ($param) {
				$ui = callSvcInt("UiMeta.query", ["res" => "id,obj,diId,fields", "cond" => ["name"=>$param["表名"]], "fmt"=>"one"]);
				$approveName = $param["表名"] . "审批";
				$conf = jsonEncode([[
					"name" => $approveName,
					"obj" => $ui["obj"],
					"field" => "approveRecId",
					"stages" => [
						[ "role" => $param["审批角色"] ]
					]
				]]);
				Cinf::setValue("conf_approve", $conf);

				// 加DI字段
				$col = [
					"name" => "approveRecId",
					"type" => "i",
					"title" => "审批记录"
				];
				$di = callSvcInt("DiMeta.get", ["res"=>"id,cols", "id"=>$ui["diId"]]);
				$cols = jsonDecode($di["cols"]);
				if (arrFind($cols, function ($e) {
					return $e["name"] == "approveRecId";
				}, $idx)) {
					$cols[$idx] = $col;
				}
				else {
					$cols[] = $col;
				}
				// 加虚拟字段, 在AC逻辑中加
				$acLogics = [[
					"class" => "AC0",
					"onInit" => '$this->vcolDefs[] = ApproveRec::vcols();'
				]];
				callSvcInt("DiMeta.set", ["id"=>$di["id"]], [
					"cols" => jsonEncode($cols, true),
					"acLogic" => jsonEncode($acLogics, true)
				]);
				callSvcInt("DiMeta.sync", ["id"=>$di["id"]]);

				// 加UI字段
				$fields = jsonDecode($ui["fields"]);
				$newFields = [
					[
						"name" => "approveRecId",
						"title" => "审批记录",
						"type" => "i",
						"notInList" => true
					],
					[
						"name" => "approveFlag",
						"title" => "审批状态",
						"type" => "flag",
						"uiType" => "combo",
						"opt" => <<<EOL
{
	disabled: true,
	enumMap: ApproveFlagMap,
	styler: Formatter.enumStyler(ApproveFlagStyler),
	formatter: function (val, row) {
		return WUI.makeLink(val, function () {
			var pf = {cond: {objId: row.id, approveFlow:row.approveFlow}};
			WUI.showPage("pageUi", {uimeta:"metaApproveRec", title: "审批记录-{$param["表名"]}" + row.id, force: 1, pageFilter: pf });
		});
	}
}
EOL,
					],
					[
						"name" => "approveEmpId",
						"title" => "审批人",
						"type" => "i",
						"notInList" => true,
						"uiType" => "combo",
						"linkTo" => "Employee.name",
						"uiType" => "combo-db",
						"opt" => "{disabled: true}"
					],
					[
						"name" => "approveDscr",
						"title" => "审批备注",
						"type" => "s",
						"uiType" => "text",
						"opt" => "{disabled: true}"
					]
				];
				foreach ($newFields as $f)
				{
					if (arrFind($fields, function ($e) use ($f) {
						return $e["name"] == $f["name"];
					}, $idx)) {
						$fields[$idx] = $f;
					}
					else {
						$fields[] = $f;
					}
				}
				callSvcInt("UiMeta.set", ["id"=>$ui["id"]], ["fields" => jsonEncode($fields, true)]);


				// 加前端页面上按钮代码
				$h5code = <<<EOL
UiMeta.on("dg_toolbar", "{$param["表名"]}", function (ev, buttons, jtbl, jdlg) {
    var btnApprove = ["approveFlow", {
        name: "$approveName",
        text: "审批",
    }];
    buttons.push(btnApprove);
});
EOL;
				callSvcInt("UiCfg.setValue", ["name"=>"h5code"], ["value" => $h5code]);

				$rv = [
					["ac" => "reloadMeta"],
					self::ret_showPage($param["表名"]),
					"<a onclick=\"JDConf('conf_approve')\">审批流配置</a>"
				];
				return $rv;
			}
		];

/*
仅用于内部批量执行命令使用

// cmd="batch", param=
{
	"list" [
		{
			"cmd": "新增表",
			"param": {
				"表名": "表1",
				...
			}
		},
		{
			"cmd": "新增表",
			"param": {
				"表名": "表2",
				...
			}
		}
	]
}

测试：
callSvr("Robot.chat", {cmd: "batch"}, $.noop, {list: [
    {cmd: "新增菜单", param: {菜单项:"设备台帐-设备管理1", 页面:"设备"}},
    {cmd: "新增菜单", param: {菜单项:"设备台帐-设备管理2", 页面:"设备"}}
]})
*/
		$this->cmds["batch"] = [
			"param" => ["list" => ["cmd", "param"]],
			"exec" => function ($param) {
				$rv = [];
				foreach ($param["list"] as $req) {
					$rv1 = $this->execCmd($req);
					if ($rv1)
					{
						foreach ($rv1 as $e) {
							$idx = array_search($e, $rv);
							if ($idx !== false)
							{
								array_splice($rv, $idx, 1);
							}
							$rv[] = $e;
						}
					}
				}
				return $rv;
			}
		];

		foreach ($this->cmds as $name => &$cmd) {
			$cmd["name"] = $name;
		}
	}

	static function checkParam($paramDef, $param, $throwEx = true) {
		$missingFields = [];
		foreach ($paramDef as $k => $v) {
			if (is_int($k)) {
				$k = $v;
			}
			self::fixKey($k, $isOpt);
			if (! $isOpt && ! array_key_exists($k, $param)) {
				$missingFields[] = $k;
				continue;
			}
			if (is_array($v)) {
				$subDef = $v;
				$subObj = $param[$k];
				if (!is_array($subObj)) {
					$missingFields[] = "{$k}(须是列表)";
					continue;
				}
				foreach ($subObj as $rowIdx=>$row) {
					$rv = self::checkParam($subDef, $row, false);
					foreach ($rv as $e) {
						$n = $rowIdx + 1;
						$missingFields[] = $k . '.' . $e . "(第{$n}行)";
					}
				}
			}
		}
		if ($missingFields) {
			$err = "缺少参数:" . join(",", $missingFields) . "<br>接口参数:" . self::descParam($paramDef);
			jdRet(E_PARAM, "checkParam fails", $err);
		}
		return $missingFields;
	}
	static function descParam($paramDef) {
		$arr = [];
		foreach ($paramDef as $k => $v) {
			if (is_int($k)) {
				$arr[] = $v;
				continue;
			}
			$k .= "(" . self::descParam($v) . ")";
			$arr[] = $k;
		}
		return join(",", $arr);
	}


	// 如果$str匹配某命令，则返回该命令及参数{cmd, param}；如果返回字符串，则表示缺少参数要求补全的提示语；返回null表示解析不了
	protected function parseCmd($s) {
		if (!preg_match('/^\w+/u', $s, $ms))
			return;
		$cmdName = $ms[0];
		if (!array_key_exists($cmdName, $this->cmds))
			return;
		$paramDef = $this->cmds[$cmdName]["param"];
		$param = [];
		$ret = ["cmd" => $cmdName, "param" => &$param];
		// k=[v], - [v], - 删除[v]
		if ($cnt = preg_match_all('/(?: (\w+)= | ^(-)[ ]+(删除)? )   (?: \[([^\[\]]+)\] | $)/umsx', $s, $msAll)) {
			$curKey = null; // subobj
			$curSub = null;
			for ($i=0; $i<$cnt; ++$i) {
				list ($k, $isSubFirst, $isDel, $v) = [$msAll[1][$i], $msAll[2][$i], $msAll[3][$i], $msAll[4][$i]];
				if ($k) {
					if ($curSub === null) {
						$param[$k] = $v;
						$curKey = $k;
					}
					else {
						$curSub[$k] = $v;
					}
				}
				else if ($isSubFirst) {
					unset($curSub);
					if (! is_array($param[$curKey]))
						$param[$curKey] = [];

					$k1 = current($paramDef[$curKey]); // 子项第1项名字，必不带"?"
					$curSub = [$k1 => $v];
					if ($isDel)
						$curSub["_delete"] = 1;
					$param[$curKey][] = &$curSub;
				}
			}
			unset($curSub);
		}
		return $ret;
	}

	// 重新生成可解析的(可被 parseCmd)的标准格式化命令字符串
	function descCmd($cmdName, $param) {
		$cmd = $this->cmds[$cmdName];
		if (!$cmd)
			jdRet(E_PARAM, "no cmd $cmdName");
		$arr = [$cmd["name"]];
		foreach ($cmd["param"] as $k => $v) {
			if (! is_array($v)) {
				$arr[] = $this->descCmd1($v, $param);
			}
			else {
				$arr[] = $this->descCmd1($k, null);
				foreach ($param[$k] as $row) {
					foreach ($v as $i=>$v1) {
						$arr[] = $this->descCmd1($v1, $row, $i==0);
					}
				}
			}
		}
		return join("，", array_filter($arr, function ($e) { return $e; }));
	}
	private function descCmd1($title, $param, $isSubFirst = false) {
		self::fixKey($title, $isOpt);
		if ($param == null)
			return $title . '=';
		if ($isOpt && !array_key_exists($title, $param))
			return;
		if ($isSubFirst) {
			if ($param["_delete"]) {
				return "\n- 删除[" . $param[$title] . "]";
			}
			return "\n- [" . $param[$title] . "]";
		}
		return $title . "=[" . $param[$title] . "]";
	}
	static function fixKey(&$k, &$isOpt) {
		// 可选参数
		$isOpt = false;
		if (substr($k, -1) == "?") {
			$isOpt = true;
			$k = substr($k, 0, -1);
		}
	}

	// 返回字符串,或数组,数组每项可以为: 字符串(直接显示),或{ac/前端指令}
	// 前端指令有: 
	// {ac:"reloadMenu"}
	// {ac:"reloadMeta", name?}  如果不指定name则重置所有uimeta
	// {ac:"syncDi", diId:100}
	function api_chat() {
		// 格式化接口(application/json或urlencoded): Robot.chat(cmd)(param...)
		if (stripos(getContentType(), "text/plain") === false) {
			$cmdName = mparam("cmd", "G");
			$cmd = $this->cmds[$cmdName];
			if (! $cmd)
				jdRet(E_PARAM, "unknown cmd `$cmdName`");
			self::checkParam($cmd["param"], $_POST);
			$rv = $cmd["exec"]($_POST);
			return $rv;
		}

		$s = getHttpInput();
		$rv = $this->parseCmd($s);
		/*
		// 错误提示
		if (is_string($rv)) {
			return $rv;
		}
		*/
		// 未匹配上
		if ($rv === null) {
			// 高级对话解析
			if (class_exists("RobotImp")) {
				$rv = RobotImp::chat($s, $this);
				addLog($_SESSION["cmd"]);
				if ($rv["chat"]) {
					return $rv["chat"];
				}
			}
			if ($rv === null) {
				$arr = ["抱歉, 没懂你的意思", "不太明白, 你要干啥?", "不明白你的意思"];
				return self::randStr($arr);
			}
		}

		try {
			$req = $rv;
			$rv = $this->execCmd($req) ?: [];
			$arr = ["搞定!", "配置完成!", "做好了。", "操作成功!"];
			array_unshift($rv, self::randStr($arr));
		}
		catch (Exception $ex) {
			$arr = ["出错了!", "操作失败!", "糟糕!"];
			logit($ex);
			$rv = [self::randStr($arr), $ex->getMessage()];
		}
		return $rv;
	}

	// req={cmd, param}
	private function execCmd($req)
	{
		if (! $req["cmd"])
			jdRet(E_SERVER, "no cmd");
		$cmd = $this->cmds[$req["cmd"]];
		if (! $cmd)
			jdRet(E_SERVER, "unknown cmd " . $req["cmd"]);
		$param = $req["param"];
		self::checkParam($cmd["param"], $param);
		return $cmd["exec"]($param);
	}

	public static function randStr($arr) {
		return $arr[rand(0,count($arr)-1)];
	}

	private static function createCol($field)
	{
		$fieldDef = $field["字段名"] ?: $field["标题"];
		$rv = parseFieldDef($fieldDef, $param["英文名"]);
		$col = [
			"name" => $rv["name"],
			"title" => $field["标题"],
			"type" => $rv["type"],
			"len" => $rv["len"],
		];
		if ($field["关联表"] && $field["关联字段"]) {
			// 每个关联字段可以是关联表中某字段的title或name, 或直接指定"关联字段name或title/关联字段在主表中的标题":
			//   {关联表: "设备", 关联字段: "设备名称 购入日期"} => {linkTo: "Equipment.name/设备名称 dt/购入日期"} (假设 name,dt 是关联表字段名,则自动找出其title)
			//   {关联表: "设备", 关联字段: "名称/设备名称 购入日期"} => {linkTo: "Equipment.name/设备名称 购入日期"}
			if (! preg_match('/^\w+$/', $field["关联表"])) {
				// TODO: 关联自己，这时数据库无记录
				$di = callSvcInt("DiMeta.query", ["cond" => ["title"=>$field["关联表"]], "fmt"=>"one?"]);
				if ($di) {
					$field["关联表"] = $di["name"];
					$cols1 = jsonDecode($di["cols"]);
					$fs = [];
					foreach (explode(" ", $field["关联字段"]) as $idx=>$f) {
						$a = explode("/", $f);
						$title = null;
						if (count($a) > 1) {
							$f = $a[0];
							$title = $a[1];
						}
						$col1 = arrFind($cols1, function ($e) use ($f) {
							return $e["title"] == $f || $e["name"] == $f;
						});
						if (! $col1) {
							$names = join(",", arrMap($cols1, function ($e) { return $e["title"]; }));
							jdRet(E_PARAM, null, "关联表中找不到字段: `{$di['title']}.{$f}`, 参考字段: $names");
						}
						if (!$title)
							$title = $col1["title"];
						if ($col1["name"] == $title) {
							$f = $title;
						}
						else {
							$f = $col1["name"] . '/' . $title;
						}
						$fs[] = $f;
					}
					$field["关联字段"] = join(' ', $fs);
				}
				else {
					// 可能是系统表, 不做处理
				}
			}
			$col["linkTo"] = $field["关联表"] . '.' . $field["关联字段"];
			// 链接字段名必须以"Id"结尾
			if (substr($col["name"], -2) != 'Id')
				$col["name"] .= "Id";
			$col["type"] = "i";
		}
		if ($field["下拉选项"]) {
			$col["enumList"] = $field["下拉选项"];
		}
		return $col;
	}

	// col={name: "itemId", title:"物料", linkTo: "ShopItem.name"} => 返回info={table: "ShopItem", alias: "item", vField: "itemName", targetField: "name"
	//      moreFields: [{name:"name", title:"物料", vField:"itemName"}]
	// }
	// col={name: "itemId", linkTo: "ShopItem.name/商品名 price/单价 curQty/库存数据"} 
	//    返回info={table: "ShopItem", alias: "item", vField: "itemName", targetField: "name", 
	//      moreFields: [{name:"name", title:"商品名", vField:"itemName"}, {name:"price", title:"单价", vField:"itemPrice"}, {name:"curQty", title:"库存数据", vField:"itemCurQty"}]
	//    }
	// col={name: "snLogId", linkTo: "SnLog.status"} => 返回info={table: "SnLog", alias: "snLog", vField: "snLogStatus", targetField: "status"}
	private static function parseLinkTo($col)
	{
		if (!preg_match('/(.*)Id$/', $col["name"], $m))
			return;
		$alias = $m[1];
		if (!preg_match('/^(\w+)\.(\S+)( .*)?$/', $col["linkTo"], $m2))
			return;
		$a = explode('/', $m2[2]);
		$name = $a[0];
		$title = $a[1] ?: $col["title"] ?: $col["name"];
		$info = [
			"table" => $m2[1],
			"alias" => $alias,
			"vField" => $alias . ucfirst($name),
			"targetField" => $name
		];
		$info["moreFields"] = [[
			"name" => $name,
			"title" => $title,
			"vField" => $info["vField"]
		]];
		if ($m2[3]) {
			$moreArr = preg_split('/\s+/', trim($m2[3]));
			foreach ($moreArr as $e) {
				$a = explode('/', $e);
				$name = $a[0];
				$title = ($a[1] ?: $a[0]);
				$info["moreFields"][] = [
					"name" => $name,
					"title" => $title,
					"vField" => $info["alias"] . ucfirst($name)
				];
			}
		}
		return $info;
	}
	private static function setLinkTo(&$vcols, $col)
	{
		$info = self::parseLinkTo($col);
		if (!$info)
			return;
		$pre = $info["alias"] . '.';
		/* 找是否有重复
		$vcol = arrFind($vcols, function ($e) {
		});
		*/
		$vcol = [
			"res" => [],
			"join" => "LEFT JOIN {$info['table']} {$info['alias']} ON {$info['alias']}.id=t0.{$col['name']}",
			"default" => true
		];
		foreach ($info["moreFields"] as $f) {
			$vcol["res"][] = [
				"def" => $info["alias"] . '.' . $f["name"],
				"name" => $f["vField"],
				"title" => $f["title"]
			];
		}
		$vcols[] = $vcol;
	}

	static function addTable($di)
	{
		// 由linkTo自动生成vcol
		$vcols = [];
		foreach ($di["cols"] as $col)
		{
			self::setLinkTo($vcols, $col);
		}
		$di["vcols"] = $vcols;
		$diId = callSvcInt("DiMeta.add", ["uniKey" => "name"], $di);
		callSvcInt("DiMeta.sync", ["id"=>$diId]);

		callSvcInt("UiMeta.add", ["uniKey" => "name"], [
			"defaultFlag" => 1,
			"name" => $di["title"],
			"diId" => $diId
		]);

		$uiName = $di["title"];
		return [
			["ac"=>"reloadMeta", "name"=>$uiName],
			["ac"=>"syncDi", "diId"=>$diId],
			self::ret_showPage($uiName),
			self::ret_showDiMeta(),
		];
	}

	static function addRelation($mainTable, $relTable, $isMainSubRelation = false, $fieldTitle = null)
	{
		$ui = callSvcInt("UiMeta.query", ["cond" => ["name"=>$mainTable], "res"=>"id,obj,diId,fields", "fmt"=>"one"]);
		$ui1 = callSvcInt("UiMeta.query", ["cond" => ["name"=>$relTable], "res"=>"id,obj,fields", "fmt"=>"one"]);
		$fields1 = jsonDecode($ui1["fields"]);
		$pre = $ui["obj"] . '.';
		$linkToField = arrFind($fields1, function ($f) use ($pre) {
			return $f["linkTo"] && startsWith($f["linkTo"], $pre);
		});
		if (!$linkToField)
			jdRet(E_PARAM, "no linkTo field", "关联表没有定义与主表的关联字段");
		if ($fieldTitle == null)
			$fieldTitle = $relTable;
		// @fields: field/uicol={name, title, type, uiType, opt, notInList, linkTo?, uiMeta?, pos?, listSeq?}
		if ($isMainSubRelation) {
			$field = [
				"name" => $fieldTitle,
				"title" => $fieldTitle,
				"type" => "subobj",
				"uiType" => "subobj",
				"uiMeta" => $relTable,
				"notInList" => true,
				"opt" => <<<EOL
{
	obj: "{$ui1["obj"]}",
	relatedKey: "{$linkToField["name"]}",
	// "valueField"
	dlg: "dlgUi_inst_{$relTable}"
}
EOL
			];
		}
		else {
			$field = [
				"name" => "v_" . $fieldTitle . "_", // v_xx_, 下划线结尾不会导出
				"title" => $fieldTitle,
				"type" => "s",
				"uiType" => null, // 明细对话框中不显示
				"opt" => <<<EOL
{
	formatter: (v,row) => {
		return WUI.makeLink("查看", function () {
			var pageFilter = {cond: {{$linkToField['name']}: row.id}};
			WUI.showPage("pageUi", {uimeta:"{$relTable}", title: "{$fieldTitle}-{$mainTable}" + row.id, pageFilter: pageFilter});
		});
	}
}
EOL
			];
		}
		$fields = jsonDecode($ui["fields"]);
		if (arrFind($fields, function ($f) use ($field) {
			return $f["name"] == $field["name"];
		}, $idx)) {
			$fields[$idx] = $field;
		}
		else {
			$fields[] = $field;
		}
		callSvcInt("UiMeta.set", ["id"=>$ui["id"]], ["fields"=>$fields]);
		return [
			["ac"=>"reloadMeta", "name"=>$mainTable],
			self::ret_showPage($mainTable),
			self::ret_showDiMeta(),
		];
	}

	static function addMenu($menuSpec, $code)
	{
		$menuStr = callSvcInt("UiCfg.getValue", ["name"=>"menu"]);
		$menu = jsonDecode($menuStr) ?: [];
		$names = explode('-', $menuSpec);
		$curArr = &$menu;
		foreach ($names as $idx=>$name) {
			$value = null;
			if ($idx == count($names)-1) { // isLeaf
				$value = $code;
			}

			$menuIdx = null;
			arrFind($curArr, function ($e) use ($name) {
				return $e["name"] == $name;
			}, $menuIdx);
			if (isset($menuIdx)) {
				if ($value) {
					$curArr[$menuIdx] = [
						"name" => $name,
						"value" => $value
					];
				}
				else {
					$curArr = &$curArr[$menuIdx]["value"];
					if (! isArray012($curArr)) {
						$curArr = [];
					}
				}
			}
			else {
				$curArr[] = [
					"name" => $name,
					"value" => $value ?: []
				];
				if ($value === null)
					$curArr = &$curArr[count($curArr)-1]["value"];
			}
		}
		unset($curArr);
		callSvcInt("UiCfg.setValue", ["name"=>"menu"], ["value" => jsonEncode($menu, true)]);

		return [
			["ac"=>"reloadMenu"],
			"<a onclick=\"UiMeta.showDlgSetMenu()\">打开菜单编辑器</a>"
		];
	}

	static function ret_showPage($uiName) {
		return "<a onclick=\"WUI.showPage('pageUi', '{$uiName}!')\">打开页面[{$uiName}]</a>";
	}
	static function ret_showDiMeta() {
		return "<a onclick=\"WUI.showPage('pageDiMeta', {force:1})\">数据模型</a>";
	}
}
