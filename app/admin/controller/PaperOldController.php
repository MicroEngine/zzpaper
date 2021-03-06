<?php
 
namespace app\admin\controller;

 
use cmf\controller\AdminBaseController; 
use think\Db; 
 
class PaperOldController extends AdminBaseController
{
    private $m;
    private $order;
    private $paper_status;
    public function _initialize()
    {
        parent::_initialize();
        $this->m=Db::name('paper_old');
        $this->order='id desc';
        $this->paper_status=config('paper_status');
        $this->assign('flag','已完成借条');
        
        $this->assign('paper_status', $this->paper_status);
    }
     
    /**
     * 已完成借条列表
     * @adminMenu(
     *     'name'   => '已完成借条列表',
     *     'parent' => 'admin/Paper/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10,
     *     'icon'   => '',
     *     'remark' => '已完成借条列表',
     *     'param'  => ''
     * )
     */
    public function index()
    { 
        $m=$this->m;
        $where=[];
        $data=$this->request->param();
        
        if(empty($data['borrower_idcard'])){
            $data['borrower_idcard']='';
        }else{
            $where['borrower_idcard']=$data['borrower_idcard'];
        }
        if(empty($data['lender_idcard'])){
            $data['lender_idcard']='';
        }else{
            $where['lender_idcard']=$data['lender_idcard'];
        }
        
        $list= $m->where($where)->order($this->order)->paginate(10);
       
        // 获取分页显示
        $page = $list->render(); 
       //得到所有管理员
       
        $this->assign('page',$page);
        $this->assign('list',$list); 
        $this->assign('data',$data); 
        
        return $this->fetch();
    }
    /**
     * 已完成借条查看
     * @adminMenu(
     *     'name'   => '已完成借条查看',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10,
     *     'icon'   => '',
     *     'remark' => '已完成借条查看',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $m=$this->m;
        
        $id=$this->request->param('id',0,'intval');
        $info=$m->where('id',$id)->find();
        if(empty($info)){
            $this->error('此借条不存在');
        }
        $this->assign('info',$info); 
        return $this->fetch();
    }
    /**
     * 已完成借条编辑执行
     * @adminMenu(
     *     'name'   => '已完成借条编辑执行',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10,
     *     'icon'   => '',
     *     'remark' => '已完成借条编辑执行',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        $this->error('暂时不能修改已还款借条');
        $m=$this->m;
        
        $data=$this->request->param();
        $where=['id'=>$data['id']];
        $info=$m->where($where)->find();
        if(empty($info)){
            $this->error('借条不存在，请刷新页面');
        }
        $statuss=$this->paper_status;
        $data['update_time']=time();
        $data_action=[
            'aid'=>session('ADMIN_ID'),
            'type'=>'paper',
            'time'=>$data['update_time'],
            'ip'=>get_client_ip(),
            'action'=>'对借条'.$data['id'].'更改状态"'.$statuss[$info['status']].'"为"'.$statuss[$data['status']].'"',
            
        ];
       
        //如果是确认还款结束的就进入借条仓库，不同意借条就24小时后删除
        Db::startTrans();
        try {
            switch($data['status']){
                case '7':
                    //$m->where($where)->delete();
                    unset($info['id']);
                    unset($info['status']);
                    unset($info['is_borrower']);
                    unset($info['is_lender']);
                    $info['update_time']=$data['update_time'];
                    Db::name('paper_old')->insert($info);
                    break;
               /*  case '1':
                    $row=$m->where($where)->update(['status'=>$data['status'],'update_time'=>$data['update_time']]);
                    if($row!=1){
                        throw new \Exception('更新失败');
                    } 
                    break; */
                default :throw new \Exception('目前只能修改为已还款');break;
           } 
           Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('保存失败！'.$e->getMessage());
        }
       
        Db::name('action')->insert($data_action);
        $this->success('保存成功！',url('index'));
         
    }
    
}
