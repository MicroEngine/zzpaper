<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\portal\controller;

use cmf\controller\HomeBaseController;
use think\db;
/*处理每日定时任务  */
class TimeController extends HomeBaseController
{
    /*处理每日定时任务  */
    public function time()
    {
        zz_log('每日任务开始','time.log');
        //
        $data_action=[];
        //获取凌晨0点时间
        $time=zz_get_time0();
        //24小时过期时间
        $time0=$time-86400;
        $time_day=trim(config('time_day'));
        //判断重复任务
        if(strtotime($time_day)===$time){
            zz_log('重复任务，结束','time.log');
            exit('重复任务，结束');
        }else{
            cmf_set_dynamic_config(['time_day'=>date('Y-m-d')]);
        }
        $ip=get_client_ip();
        set_time_limit(600);
        $db=config('database'); 
        $mysqli=new \mysqli($db['hostname'],$db['username'],$db['password'],$db['database'],$db['hostport']);
        $mysqli->set_charset($db['charset']);
         
        
        $m_user=Db::name('user');
        //1更新 了登录失败次数
        $rows=$m_user->where('login_fail','gt',0)->update(['login_fail'=>0]);
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'清空了登录失败次数'.$rows.'条',
        ];
        zz_log('清空了登录失败次数'.$rows.'条','time.log');
        
        //2删除过期申请
        $m_reply=Db::name('reply');
        $where_reply1=[
            'is_overtime'=>['eq',1],
            'update_time'=>['elt',$time0]
        ];
        $rows=$m_reply->where($where_reply1)->delete();
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'删除了过期申请'.$rows.'条',
        ];
        zz_log('删除了过期申请'.$rows.'条','time.log');
        
        //3更新过期申请
        $where_reply2=[ 
            'is_overtime'=>['eq',0],
            'update_time'=>['elt',$time0]
        ];
        $rows=$m_reply->where($where_reply2)->update(['is_overtime'=>1,'update_time'=>$time]);
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了过期申请'.$rows.'条',
        ];
        zz_log('更新了过期申请'.$rows.'条','time.log');
         
        
        //借条处理
        $m_paper=Db::name('paper');
        //1先删除过期借条 
        $where_paper1=[
            'status'=>['eq',2],
            'update_time'=>['elt',$time0]
        ];
        $rows=$m_paper->where($where_paper1)->delete();
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'删除了过期借条'.$rows.'条',
        ];
        zz_log('删除了过期借条'.$rows.'条','time.log');
        
        //2借条逾期天数更新
        $rows=$m_paper->where('status',5)->setInc('overdue_day');
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了借条逾期天数'.$rows.'条',
        ];
        zz_log('更新了借条逾期天数'.$rows.'条','time.log');
        
        //3更新用户逾期7天的次数,还有金额
        $list_overdue7=$m_paper->field('borrower_id,money')->where(['status'=>5,'overdue_day'=>7])->column('');
        $sql_overdue7='insert into cmf_user(id,overdue7,overdue7_money) values';
        $tmp='';
        $rows=0;
        foreach($list_overdue7 as $v){ 
            $tmp.=',('.$v['borrower_id'].',1,'.$v['money'].')';
        } 
        if(!empty($tmp)){
            $tmp=substr($tmp, 1);
            $sql_overdue7=$sql_overdue7.$tmp.' on duplicate key update overdue7=1+overdue7,overdue7_money=values(overdue7_money)+overdue7_money;';
            $mysqli->query($sql_overdue7);
            $rows=$mysqli->affected_rows;
        } 
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了用户逾期7天的次数和金额'.$rows.'条',
        ];
        zz_log('更新了用户逾期7天的次数和金额'.$rows.'条','time.log');
        
        //4更新用户逾期累计和今日到期为逾期
        //昨日到期的就是新的逾期
        $list_overdue1=$m_paper->field('borrower_id,money')->where(['status'=>3])->column('');
        $sql_overdue1='insert into cmf_user(id,overdue1,overdue1_money) values';
        $tmp='';
        $rows=0;
        foreach($list_overdue1 as $v){
            $tmp.=',('.$v['borrower_id'].',1,'.$v['money'].')';
        }
        if(!empty($tmp)){
            $tmp=substr($tmp, 1);
            $sql_overdue7=$sql_overdue7.$tmp.' on duplicate key update overdue1=1+overdue1,overdue1_money=values(overdue1_money)+overdue1_money;';
            $mysqli->query($sql_overdue1);
            $rows=$mysqli->affected_rows;
        } 
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了用户逾期累计'.$rows.'条',
        ];
        zz_log('更新了用户逾期累计'.$rows.'条','time.log');
        
        $rows=$m_paper->where('status',3)->update(['status'=>5,'overdue_day'=>1]);
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新今日到期为逾期'.$rows.'条',
        ];
        zz_log('更新今日到期为逾期'.$rows.'条','time.log');
        
        ////5更新还剩1天的借条今日到期 
        $rows=$m_paper->where(['status'=>4,'expire_day'=>1])->update(['status'=>3,'expire_day'=>0]);
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了还剩1天的借条今日到期'.$rows.'条',
        ];
        zz_log('更新了还剩1天的借条今日到期'.$rows.'条','time.log');
        
         
        //6更新借条即将到期天数
        $rows=$m_paper->where('status',4)->setDec('expire_day');
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新了即将到期天数'.$rows.'条',
        ];
        zz_log('更新了即将到期天数'.$rows.'条','time.log');
        
        //7更新借条发起和借条不同意为过期
         
        $where_overtime=[
            'status'=>['in',[0,1]],
            'update_time'=>['elt',$time0],
        ];
        $rows=$m_paper->where($where_overtime)->update(['status'=>2,'update_time'=>$time]);
        $data_action[]=[
            'aid'=>1,
            'time'=>time(),
            'ip'=>$ip,
            'type'=>'system',
            'action'=>'更新借条发起和借条不同意为过期'.$rows.'条',
        ];
        zz_log('更新借条发起和借条不同意为过期'.$rows.'条','time.log');
        Db::name('action')->insertAll($data_action);
        zz_log('end','time.log');
        $mysqli->close();
        $sleep=$time+3600*24+2-time();
        zz_log("sleep时间".($sleep/3600)."小时",'3','time.log');
        echo "sleep时间".($sleep/3600)."小时";  
        sleep($sleep);
        $url=url('portal/time/time','',true,true);
        file_get_contents($url);
       exit('执行结束');
    }
}
