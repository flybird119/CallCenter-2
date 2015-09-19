<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<head>
<meta http-equiv="content-type" content="text/html; charset=<?php echo config_item('charset');?>" />
<base href="<?php echo $this->config->item('base_url') ?>/"/>

<link rel="stylesheet" href="www/css/main.css" type="text/css" media="screen" />
<link rel='stylesheet' href='www/lib/jquery/ui/themes/base/jquery.ui.all.css'   type='text/css'/>

<script type="text/javascript" src="www/lib/jquery.js"></script>
<script type="text/javascript" src="www/lib/dataTable/js/jquery.dataTables.js"  ></script>
<script src="www/js/work.js"  type="text/javascript"></script>
<script type='text/javascript' src='www/lib/jquery/jquery-ui-1.8.16.custom.js'></script>
<script type='text/javascript' src='www/lib/extenal.js'></script>
<script type='text/javascript' src='www/lib/json2.js'></script>


<style type="text/css" title="currentStyle">
			@import "www/lib/dataTable/css/demo_page.css";
			@import "www/lib/dataTable/css/demo_table.css";
.dataTables_filter{display:none}
.dataTables_length{display:none}
</style>

<script>
$(document).ready(function() {
	$('#agentId').attr('value','<?php echo $agentId?>');
	setDatePickerLanguageCn();	
	
	$("#start_ymd").datepicker(); 
	$("#end_ymd").datepicker();  
	
	var ctime=new Date();
	$("#start_ymd").attr('value', ctime.format('yyyy-MM-dd'));	
	$("#end_ymd").attr('value', ctime.format('yyyy-MM-dd'));
	
	var gTargetAgents=<?php echo json_encode($targetAgents);?>;
	$.each(gTargetAgents,function(index,row){
		if(row.name_text != '未填写')
			$('#targetAgent').append("<option value='"+row.name_value+"'>"+row.name_text+"</option>");
		else
			$('#targetAgent').append("<option selected='selected' value='"+row.name_value+"'>"+row.name_text+"</option>");
		
	});
	//招生状态赋值
	function getDateString(ymd, hour, minut){
	 	return ymd+" "+hour+":"+minut+":00";
	}	 
	 
	
	function getSearchString(){		
		$seachValue=$('#searchText').attr('value');	
		//filterString='{"searchType":0,"agentId":\"'+$('#agentId').attr('value')+'\","searchText":[["and","int","call_type","0"],["nand","varchar","status","CONNECTED"],["or","varchar","agent","'+$seachValue+'"],["or","varchar","name","'+$seachValue+'"],["or","varchar","phone_number","'+$seachValue+'"]]}';	
		var timeSearch=[];
		timeSearch.push("and");
		timeSearch.push("datetime");
		timeSearch.push("link_stime");
			
		timeSearch.push(getDateString($('#start_ymd').attr('value'), $('#s_hour').val(),$('#s_min').val()));
		timeSearch.push(getDateString($('#end_ymd').attr('value'), $('#e_hour').val(),$('#e_min').val()));
		filterString={"searchType":0,"agentId":"","searchText":""};
		
		timeSearch.push(getDateString($('#start_ymd').attr('value'), $('#s_hour').val(),$('#s_min').val()));
		timeSearch.push(getDateString($('#end_ymd').attr('value'), $('#e_hour').val(),$('#e_min').val()));
		
		filterString={"searchType":1,"agentId":"","searchText":[]};
		filterString.agentId=$("#agentId").attr("value");
		searchItem=[];
		
		if($("#targetAgent").val() != "全部" && $("#targetAgent").val() != "未填写"){
			var agentSearch=["and","varchar","agent",""];
			agentSearch[3]=$("#targetAgent").val();
			searchItem.push(agentSearch);
		}
		
		
		if($("#alloted").val() != "0" ){
			var allotedSearch=["and","varchar","alloted",""];
			allotedSearch[3]=$("#alloted").val();
			searchItem.push(allotedSearch);
		}
		
		var phoneNumberSearch=["likeand","varchar","phone_number",""];	
		phoneNumberSearch[3]=$("#phoneNumberText").attr("value");
		
		searchItem.push(timeSearch);
		searchItem.push(phoneNumberSearch);
		searchItem.push(["nand","varchar","status","CONNECTED"]);
		searchItem.push(["and","varchar","call_type","callin"]);
		
		filterString.searchText=searchItem;
		return JSON.stringify(filterString);
	}	
	createTables=function (filterString){
		$('#dataList').dataTable( {
			"bProcessing": true,
			"bServerSide": true,
			"bStateSave" : false,
			"fnCreatedRow": function( nRow, aData, iDataIndex ) {
			  // Bold the grade for all 'A' grade browsers 
			   	$('td:eq(3)', nRow).html('呼入');
				$('td:eq(4)', nRow).html('漏电');
    		},"aoColumns": [
				{"bSortable":false,"mDataProp":"0"},
				{"mDataProp":"1"},
				{"mDataProp":"2"},
				{"mDataProp":"3"},
				{"mDataProp":"4"},
				{"mDataProp":"5"},
				{"mDataProp":"6"},
				{"mDataProp":"7"}
			],
			"iDisplayLength": 10,
			"fnServerParams": function (aoData) {
				var externData={ "name": "filterString", "value": "my_value" };
				externData.value=filterString;
				aoData.push(externData);
			},
			"sAjaxSource": "<?php echo site_url('report/ajaxReportMisscall')?>",
			"oLanguage": {"sUrl": "<?php echo $this->config->item('base_url')?>/www/lib/dataTable/de_DE.txt"}
    	}); 
	}	
	createTables(getSearchString());
	$("#btnSearch").click(function(){
		filterString=getSearchString();
		var oTable = $('#dataList').dataTable();
		oTable.fnDestroy();	
		createTables(filterString);	
	});	
	
	//导出文件
	$("#btnExport").click(function(){	
		 var req={"filterString":""}
		 req.filterString=getSearchString();
		 $("#csvUrl").html("");
		 $.post("<?php echo site_url('export/ajaxMissCallExport')?>",req,function(res){	
			$("#csvUrl").attr("href", res.path);
			$("#csvUrl").html(res.fileName);					  							
		});  	
	});	
	
	//分配坐席
	$("#btnAllot").click(function(){	

		var req={}
		var filterString=getSearchString();

		$("#filterString").val(filterString);
		
		var agent_count = 0;
		var agent_data;
		
		/*	
		var diag =new Dialog("allot");
		diag.Width = 620;
		diag.Height = 320;
	 	diag.Title = "选择分配坐席";

		diag.URL = "<?php echo site_url('report/allotAgents')?>";
		diag.show(); 	
		*/
		$.post("<?php echo site_url('report/ajaxGetAllAgents')?>",req,function(res){					
			display_agents(res.data);				
		},"JSON");
		
		$("#dialog-confirm").dialog("open");

	  
	  
	});	
	

		
	 // 初始化对话框
 $("#dialog-confirm").dialog(
 {
     modal: true,             // 创建模式对话框
     autoOpen: false,
     width:540,
		 //minHeight:320,
		 height:420,

     buttons: {
         "确认": function() {
         			subAllot();
              $(this).dialog('close');
              $("#btnSearch").click();
         },
         "取消": function() {
             $(this).dialog('close');
         }
     }
 }); 
 



	$("#allselect").bind("click", function () {	
  	$("[name = box]:checkbox").attr("checked", this.checked);
  });
 
 	
});

function display_agents(list){ //显示字段名
		$("#tb_agents  tr:not(:first)").empty();
		$.each(list, function(index,values){	
			var str = '<tr><td><input name="box" type="checkbox" value="' + values["code"] + '" /></td>';
			str += '<td>'+values["code"]+'</td>';
			str += '<td>'+values["name"]+'</td></tr>';
			
			$("#tb_agents tr:last").after(str);

		});
}
function subAllot(){ 
	//获取filting

	var Req={"filterString":"", "agents":""};
	Req.filterString=$("#filterString").val();
	
	//获取agents
	var agentsdata = new Array();
	$("input[name='box']").each(function(){

    if ("checked" == $(this).attr("checked")) {
          agentsdata.push($(this).val());
          //alert($(this).val());
    }
  });
	
	//post
  Req.agents=JSON.stringify(agentsdata);
  //alert(agentsdata);
  
	$.post("<?php echo site_url('report/ajaxAllotMisscall')?>",Req,function(res){					
		//alert(res.Remainder);
		if(res.ok == 1){			
			if(res.left == 0){
				alert("分配完成！");
			}else{
				alert("分配完成！余数:"+res.left+" 条未分配，请重新选择 "+res.left+ " 名坐席进行分配。");			
			}			
		}
	},"JSON");

}
	
</script>    


<style type="text/css">
	#dialog-confirm{width:50%;float:center;margin-top:10px; margin-left:10px}
</style>
	
</head>
<body>
<div><input type="hidden" value="" id="agentId"></div>
<div class="page_main page_tops" >
	<div class="page_nav">
    
         <div class="nav_ico"><img src="www/images/page_nav_ico.jpg" /></div>
         <div class="nav_">当前位置： &gt; 所有客户</div>
         <div class="nav_other"></div>
	</div>
    <div class="func-panel">
			 <div class="left">
             	电话号码：<input type="text" id="phoneNumberText"> 	
                从
         	 	<input type="text" name="start_ymd"   id="start_ymd" value="" style="width:80px"/> 
         	 	<?php echo form_dropdown('s_hour',$beginTime['hourOptions'],$beginTime['hourDef'],'id="s_hour"')?><?php echo form_dropdown('s_min',$beginTime['minOptions'],$beginTime['minDef'],'id="s_min"');?>
       	 	 	到<input type="text" name="end_ymd"   id="end_ymd" value="" style="width:80px"/>
		 	 	 <?php echo form_dropdown('e_hour',$endTime['hourOptions'],$endTime['hourDef'],'id="e_hour"');?><?php echo form_dropdown('e_min',$endTime['minOptions'],$endTime['minDef'],'id="e_min"'); ?>
          
			  
             	坐席：<select id="targetAgent" name="targetAgent"></select>
             	分配状态：<select id="alloted" name="alloted">
             							<option value="0" selected="">全部</option>
													<option value="N">未分配</option>
													<option value="Y">已分配</option>
             						</select>
                <input type="button" id="btnSearch" value="搜索" class="btnSearch"/>
                <input type="button" id="btnExport" value="导出" class="btnSearch"/>
                
                <a id="csvUrl" href='export_datas/clients_09Apr12.csv'></a>
                
                
                <input type="button" id="btnAllot" value="分配坐席" class="btnSearch"/>
			 </div>
			 <div align='right' class="right" ></div>	
			 <div style="clear:both;"></div>  
	</div>	
    <div id="example" style='display:block'>
          <table width="100%" cellpadding="0" cellspacing="0" border="0"  id="dataList" >
          		<thead>
                	<tr>
                	<th  align="left" width="80px">坐席工号</th>
                    <th  align="left"  width="80px">坐席名字</th>
                    <th  align="left" width="100px">对方电话</th>
                    <th	align="left" width="40px">类型</th>    
                    <th	align="left">状态</th> 
                     <th width="left" width="80px">400号码</th>

                    <th align="left" width="120px">时间</th>
                    <th align="left" width="80px">是否已分配</th>
                 	<!--th width="60px">操作</th-->
                    </tr>               
                </thead>
          </table>
      </div>
</div>

 <div id="dialog-confirm" title="选择坐席" style="display: none">
    <input type="hidden" value="" id="filterString">
		<div id="allot_content" >
			<div>坐席列表：<input id="allselect" name="allselect" type="checkbox" />全选 </div>
			<table id="tb_agents" class="dataTable">
		
			<tr><td><font size="4">选择</font></td><td><font size="4">坐席ID</font></td><td><font size="4">坐席名称</font></td></tr>
			<tbody> </tbody>
		
			</table>
		</div>
 </div>
 
</body>
</html>