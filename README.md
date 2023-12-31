# AI助手 - 通过人机交互渐近式创建应用系统

通过人机交互来搭建企业业务系统原型。操作人可通过语音或文字与系统AI助手进行需求描述，AI助手在了解并确认用户需求后，生成业务系统原型。
渐进式创建应用，交互式优化应用。

结合敏捷开发平台的低代码、实时建模、人工智能引擎的特性，展现敏捷开发平台面向未来工业人工智能应用趋势的现阶段成果。

## 功能

以开发模式打开管理端应用（指URL中要加`?dev`参数，激活开发菜单），打开菜单【系统设置-开发-AI助手】，是一个聊天机器人窗口，通过对话，可以实现以下主要功能：

- 数据模型类
	- 新增表（新增时，若对象已存在则覆盖，下同）
	- 新增菜单
	- 修改字段
	- 添加领域模型
	- 新增关联
	- 新增子表关联

- 业务引擎类
	- 图像检测
	- 统计报表
	- 审批流程

具体可查看【AI助手】对话框中的示例。

AI助手的功能接口，数据模型类依托于筋斗云平台二次开发引擎，业务引擎类由平台内置功能（如统计报表）或各类业务插件提供（如审批流程、图像检测等）。

## 接口协议设计

使用Robot.chat接口:

	POST Robot.chat
	Content-Type: text/plain

	（用户发送的聊天内容，示例：）
	新增菜单，菜单项=[设备台帐-设备管理], 页面=[设备]

返回内容（指返回数组的第2元素，第1元素是返回码，0为成功）可以是个字符串，或数组。字符串将直接做为聊天响应，显示在对话框中。
数组每一项可以是个字符串（聊天响应），也可以个是指令对象（具有ac参数，由前端自动执行，不显示）。

返回示例：

	[0, "完成!"]

或

	[0, [
		"完成！",
		{"ac": "reloadMenu"},
		"<a href=\"javascript:UiMeta.showDlgSetMenu()\">打开菜单编辑器</a>"
	]]

注意：

- 字符串可以是html链接，前端将直接显示为链接，并可以点击执行。
- 前端指令有: 

		{ac:"reloadMenu"} // 刷新菜单
		{ac:"reloadMeta", name?}  // 刷新某UiMeta（通过name指定），没有name表示刷新所有UiMeta
		{ac:"syncDi", diId:100}

Robot.chat接口也可用于直接调用功能接口(URL参数cmd指定功能接口名)，功能参数通过JSON格式传入：

	POST Robot.chat?cmd=新增菜单
	Content-Type: application/json

	{
		"菜单项": "设备台帐-设备管理",
		"页面": "设备",
	}

此时直接执行“新增菜单”命令并返回。

## 功能接口

### 新增表

接口参数：

	[ "表名", "英文名", "菜单项?", "字段列表" => ["标题", "字段名?", "关联表?", "关联字段?", "下拉选项?"] ],

问号结尾是可选参数，`a=>b`形式表示a为数组参数名，数组中的每一项须包含b中所列参数。

对话示例：

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

### 菜单修改

接口参数：

	[ "菜单项", "页面" ]

对话示例：

	新增菜单，菜单项=[设备台帐-设备管理], 页面=[设备]

JSON格式：

	{
		菜单项: "设备台帐-设备管理",
		页面: "设备",
	}

- 页面：即此前创建的表，表名(title，不是表的英文名/name)就是页面名。

### 修改字段

用于对已存在的表，添加、删除或修改字段。
若指定字段在表中不存在则添加，存在则覆盖更新。

接口参数：（与新增表类似，字段列表部分相同）

	[ "表名", "字段列表" => ["标题", "字段名?", "链接字段?", "下拉选项?"]]

对话示例：

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

### 新增子表关联

对于主-子关系的两张表，打开主表的明细对话框，在对话框下方显示子表。

接口参数：

	["表名", "关联表名"]

对话示例：

	新增子表关联，表名=[设备]，关联表名=[设备巡检记录]

JSON格式：

	{
		表名: "设备",
		关联表名: "设备巡检记录"
	}

注意：两张表应提前创建好，其中关联表中，必须有字段关联到主表（即字段指定过“关联表”和“关联字段”参数），否则执行时将报错。
例如，在“设备巡检记录”表中，有个“设备Id”字段关联到“设备”表。示例：

	新增表，表名=[设备巡检记录]
	- [设备]，关联表=[设备]，关联字段=[设备名称 购入日期]

注意：关联到其它表的字段必须以Id结尾，AI助手会检查如果不是Id结尾，则自动加上Id。示例中的实际关联关系为：`设备巡检记录.设备Id=设备.id` （为方便理解此处直接用的表名，实际数据库中表名使用英文名，为`CheckRecord.设备Id=Equipment.id`）

### 新增关联

对于有关联关系的两张表，在主表的列表页中，增加一列显示关联表链接，点链接可跳转打开该行记录对应的关联表记录。

接口参数：

	["表名", "关联表名"]

对话示例：

	新增关联，表名=[设备]，关联表名=[设备巡检记录]

JSON格式：

	{
		表名: "设备",
		关联表名: "设备巡检记录"
	}

新增关联和新增子表关联本质上是相同的，只是UI展现形式有区别，子表是在主表的详情对话框中展现，关联表是点主表中的链接时打开；甚至可以同时使用关联和子表关联。

### 添加领域模型

接口参数：

	[ "领域模型名" ]

对话示例：

	添加领域模型，领域模型名=[通用仓储WMS]

JSON格式：

	{
		领域模型名: "通用仓储WMS"
	}

领域模型的实质就是addon, 可以包含一系列数据表(DiMeta)、页面(UiMeta)、菜单项、前后端定制逻辑等，自动加载路径为`addon/{领域模型名}.xml`.
注意目前系统限制只能安装一个addon，再安装会清空之前的addon。

### 图像检测

对一个图片附件字段，在上传图片时自动进行图像检测，把带有标注结果的输出图记录到另一个输出图字段。
模型基于yolov8n，可以自己训练。

接口参数：

	[ "表名", "源图片字段", "输出图字段", "模型?" ]

对话示例：

	图像检测，表名=[设备]，源图片字段=[设备图]，输出图字段=[缺陷图]，(可选)模型=[yolov8n]

JSON格式：

	{
		表名: "设备",
		源图片字段: "设备图",
		输出图字段: "缺陷图",
		模型: "yolov8n"
	}

图像检测使用了class/AiDetect.php类，它依赖于yb-detect项目提供的功能。目前使用固定的URL来访问接口："http://localhost:8000/"。
yb-detect提供生产环境下由c++/php/c#实现的检测服务, 使用onnx格式的模型(实际上由aidetect项目训练后导出); 
安装及编译详见[yb-detect手册](yb-detect/README.md)，须安装opencv库和onnxruntime库等。

集成部署：

	cd yb-detect/cpp
	# 编译
	make
	# 下载通用模型
	wget http://yibo.ltd/share/model/yolov8n.onnx
	# 链接应用的upload目录过来，示例:
	ln -sf /var/www/src/rt-demo/server/upload ./
	# 运行web服务
	php -S 0.0.0.0:8000

接口测试：放一个图片如1.jpg在yb-detect/cpp目录下：

	curl "localhost:8000/?model=yolov8n&image=1.jpg"

### 统计报表

接口参数：

	[ "表名", "菜单项", "行统计字段?", "列统计字段?" ]

对话示例：

	新增统计报表，表名=[设备巡检记录]，菜单项=[设备台账-巡检月报表]，（可选）行统计字段=[巡检时间-年月 设备]，（可选）列统计字段=[巡检结果]",

JSON示例：

	{
		表名: "设备巡检记录",
		菜单项: "设备台账-巡检月报表",
		行统计字段: "巡检时间-年月 设备",
		列统计字段: "巡检结果",
	}

行统计字段或列统计字段均可以指定多个，中间以空格分隔。可以指定一个时间统计字段，比如"巡检时间-年月"表示以"巡检时间"为时间统计字段，按"年月"进行统计，这里"年月"还可以改为"年月日", "年月日时", "年季度", "年周"。
生成的统计报表，可以使用自定义报表工具继续完善。

### 审批流程

在指定的表上，生成审批流程，列表页上会显示“审批”按钮，可以查看审批记录。

对话示例：

	新增审批流程，表名=[设备巡检记录]，审批角色=[经理]

JSON示例：

	{
		表名: "设备巡检记录",
		审批角色: "经理"
	}

这里生成最简单的审批例子，高级配置如条件审批、多级审批、审批完成后的自定义操作等，均可以在“配置审批流”中操作。

注意：在发起审批时，必须存在指定审批角色的员工，否则找不到审批人而报错。

### batch批量执行接口

这是个特殊的接口，用于批量执行多个功能接口命令，比如一次创建多个表、建立多个关联等。

接口参数：

	["list" => ["cmd", "param"]]

JSON参数示例：  cmd="batch", param=

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

测试示例：

	callSvr("Robot.chat", {cmd: "batch"}, $.noop, {list: [
		{cmd: "新增菜单", param: {菜单项:"设备台帐-设备管理1", 页面:"设备"}},
		{cmd: "新增菜单", param: {菜单项:"设备台帐-设备管理2", 页面:"设备"}}
	]})

## 实现机制

- 前端对话框: page/dlgRobot.js
- 接口功能实现: class/AC_Robot.php
- 智能对话应用：class/RobotImp.php

前端发起聊天时，由AC_Robot类实现解析逻辑：

- 如果解析得到执行某个功能命令，则直接执行；若缺少参数，或执行异常，会报错返回聊天。
- 在无法解析时，使用RobotImp类进行扩展对话，实现智能聊天功能，支持上下文相关聊天、执行命令前确认等。
	它对用户意图进行语义解析，如果匹配到命令，但参数不全，应继续提问让用户补全参数，直到参数满足功能命令的需求；执行功能命令前，应得到用户确认。
	如果无法匹配功能接口，则可能是用户提出的未知需求，将扩展调用第三方智能平台（目前对接商汤日日新平台），获取实现该用户需求所需要哪些表，返回给用户确认并创建。

RobotImp提供内部函数接口给AC_Robot调用：

	RobotImp::chat($str, $robotAcObj)

$str是用户提交的聊天内容，$robotAcObj是AC_Robot类，它提供若干函数可供RobotImp内部调用。

RobotImp返回内容必须为以下形式之一：

	{ chat: "xxx" } 表示将字符串直接返回到前端，作为聊天返回内容。
	{ cmd: "xxx", param: {...} } AC_Robot将执行cmd指定的功能接口，包括batch接口（用于批量执行多个功能），并将执行结果返回聊天窗口。
	null  表示无法处理，将由AC_Robot返回出错内容。

