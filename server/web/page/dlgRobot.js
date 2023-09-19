function initDlgRobot()
{
	var jdlg = $(this);
	var jreq = jdlg.find("#req");

	jdlg.on("beforeshow", onBeforeShow);
	jdlg.on("show", onShow);
	jdlg.on("validate", onSendReq);

	var exampleMap = {
		"新增表": `新增表，表名=[设备]，英文名=[Equipment]，(可选)菜单项=[设备台账-设备管理]，字段列表=
- [设备名称]，（可选，不指定则与前面相同）字段名=[name]
- [购入日期]
- [地点]
- [状态]，下拉选项=[良好 故障]（复杂示例: [OK=良好 NOK=故障]）
- [操作人]，关联表=[Employee]，关联字段=[name]
`,
		"新增菜单": `新增菜单，菜单项=[设备台账-设备管理], 页面=[设备]`,

		"修改字段": `修改字段，表名=[设备]，字段列表=
- [设备图]
- [缺陷图]
- 删除[地点]
`,

		"图像检测": `图像检测，表名=[设备]，源图片字段=[设备图]，输出图字段=[缺陷图]，(可选)模型=[yolov8n]`,

		"添加领域模型": "添加领域模型，领域模型名=[设备巡检]",

		"新增表2": `新增表，表名=[设备巡检记录], 英文名=[CheckRecord], 字段列表=
- [设备]，（可选）字段名=[equId]，关联表=[设备]，关联字段=[设备名称 购入日期]
- [巡检时间]
- [巡检人]，关联表=[Employee]，关联字段=[name]
- [巡检结果]，下拉选项=[良好 故障]
`,
		"新增关联": "新增关联，表名=[设备]，关联表名=[设备巡检记录]",
		"新增子表关联": "新增子表关联，表名=[设备]，关联表名=[设备巡检记录]",
		"统计报表": "新增统计报表，表名=[设备巡检记录]，菜单项=[设备台账-巡检月报表]，（可选）行统计字段=[巡检时间-年月 设备]，（可选）列统计字段=[巡检结果]",
		"审批流程": "新增审批流程，表名=[设备巡检记录]，审批角色=[经理]"
/*
		"关联字段": `修改字段，表名=[设备]，字段列表=
- [最新巡检记录]，关联表=[设备]，关联字段=[巡检时间 巡检结果]，自动更新=[Yes]
`,
*/
	};
	var menuCode = $.map(exampleMap, function (v, k) {
		return '<div>' + k + '</div>';
	}).join("\n");
	var jmenuEx = $('<div style="width:150px;display:none">' + menuCode + '</div>');
	jmenuEx.menu({
		onClick: function (o) {
			showExample(o.text);
		}
	});

	jdlg.on("dblclick", "#content .req", function () {
		var t = this.firstChild.nextSibling.textContent;
		jreq.val(t);
	});

	function onBeforeShow(ev, formMode, opt) {
		var btnExample = {text: "示例: 新增表", iconCls:'icon-more', class:"splitbutton", menu: jmenuEx, handler: function () {
			showExample("新增表");
		}};
		opt.buttons = [ btnExample ];
	}

	function showExample(key) {
		jreq.val(exampleMap[key]);
		jreq.focus();
	}

	function onShow(ev) {
		jreq.focus();
	}

	function onSendReq(ev) {
		var req = jreq.val().trim();
		if (!req) {
			jreq.focus();
			return;
		}
		addChat(req, 'req');
		jreq.val("");

		callSvr("Robot.chat", api_RobotChat, req, {
			contentType: "text/plain"
		});
	}

	function api_RobotChat(res) {
		addChat(res, 'res');
	}

	// type: 'req'|'res'
	function addChat(s, type) {
		if ($.isArray(s))
			s = handleReplyArr(s);
		var jo = jdlg.find("#content");
		var who = type=="req"? "<span>我: </span>": "<span>AI助手: </span>";
		jo.append("<pre class='" + type + "'>" + who + s + "</pre>");
		jo.prop("scrollTop", jo.prop("scrollHeight"));
		jreq.focus();
	}

	function handleReplyArr(arr) {
		var s = null;
		$.each(arr, function (i, e) {
			if ($.isPlainObject(e) && e.ac) {
				handleReplyAc(e);
				return;
			}
			if (!e || typeof(e) != "string") {
				return;
			}
			if (s == null)
				s = e;
			else
				s += ' '+e;
		});
		return s;
	}

	async function handleReplyAc(e) {
		if (e.ac == "syncDi") {
			WUI.assert(e.diId);
			var di = await callSvr("DiMeta.get", {id: e.diId});
			var ui0 = await callSvr("UiMeta.query", {cond: {diId: e.diId, defaultFlag:1}, res:"t0.*", fmt: "one?"});
			var ui = $.extend(true, {}, ui0);
			UiMeta.syncDi(di, ui, true);
			if (ui0.id) {
				// 如果没有变化则不提交
				$.each(ui, (k, v) => {
					if (v == ui0[k])
						delete ui[k];
				});
				if (! $.isEmptyObject(ui)) {
					await callSvr("UiMeta.set", {id: ui0.id}, $.noop, ui);
				}
			}
			else {
				ui.defaultFlag = 1;
				ui.diId = di.id;
				await callSvr("UiMeta.add", $.noop, ui);
			}
			//WUI.unloadDialog(true);
			UiMeta.reloadUiMeta();
			console.log("同步完成!");
		}
		else if (e.ac == "reloadMeta") {
			if (e.name) {
				delete(UiMeta.metaMap[e.name]);
				WUI.reloadDialog($("#dlgUi_inst_" + e.name));
			}
			else {
				var url = WUI.makeUrl("UiCfg.script");
				WUI.loadScript(url, {async: false});
				UiMeta.reloadUiMeta();
			}
		}
		else if (e.ac == "reloadMenu") {
			var data = await callSvr("UiCfg.getValue", {name: "menu"});
			var menu = JSON.parse(data);
			UiMeta.handleMenu(menu);
		}
	}
}
