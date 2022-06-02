<?php
namespace webolc\IpfsStorage;

use Exception;
use think\admin\Storage;
use think\admin\extend\HttpExtend;
use think\Container;

/**
 * IPFS服务器存储
 * Class IpfsStorage
 * @package think\admin\storage
 */
class IpfsStorage extends Storage
{

    /**
     * 初始化入口
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize()
    {
        $type = sysconf('storage.ipfs_http_protocol') ?: 'follow';
        if ($type === 'follow') $type = $this->app->request->scheme();
        $this->prefix = trim(dirname($this->app->request->baseFile(false)), '\\/');
        if ($type !== 'path') {
            $domain = sysconf('storage.ipfs_http_domain') ?: $this->app->request->host();
            if ($type === 'auto') {
                $this->prefix = "//{$domain}";
            } elseif (in_array($type, ['http', 'https'])) {
                $this->prefix = "{$type}://{$domain}";
            }
        }
    }

    /**
     * 获取当前实例对象
     * @param null|string $name
     * @return static
     * @throws \think\admin\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function instance(?string $name = null)
    {
        if (class_exists($object = "webolc\\IpfsStorage\\IpfsStorage")) {
            return Container::getInstance()->make($object);
        } else {
            throw new Exception("File driver IpfsStorage does not exist.");
        }
    }

    /**
     * 上传文件内容
     * @param string $name 文件名称
     * @param string $file 文件内容
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null): array
    {
        $time = time();
        $file = ['field' => "file", 'name' => $name, 'content' => $file,''];
        $data = ['sign' => $this->getSign($time),'api_user'=>$this->getUser(),'time'=>$time,'filename'=>$name];
        $result = HttpExtend::submit($this->upload(), $data, $file, [], 'POST', false);
        return json_decode($result, true);
    }

    /**
     * 根据文件名读取文件内容
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function get(string $name, bool $safe = false): string
    {
        $url = $this->prefix.'/file/'. $name . "?e=" . time();
        return static::curlGet($url);
    }

    /**
     * 删除存储的文件
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function del(string $name, bool $safe = false): bool
    {
        $time = time();
        $data = ['sign' => $this->getSign($time),'api_user'=>$this->getUser(),'time'=>$time,'filename'=>$name];
        return json_decode(HttpExtend::post($this->prefix.'/del/', $data), true);
    }

    /**
     * 检查文件是否已经存在
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return boolean
     */
    public function has(string $name, bool $safe = false): bool
    {
        return is_array($this->info($name, $safe));
    }

    /**
     * 获取文件当前URL地址
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return string
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        return $this->path($name);
    }

    /**
     * 获取文件存储路径
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @return string
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->prefix.'/file/'.$name;
    }

    /**
     * 获取文件存储信息
     * @param string $name 文件名称
     * @param boolean $safe 安全模式
     * @param null|string $attname 下载名称
     * @return array
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        $time = time();
        $data = ['sign' => $this->getSign($time),'api_user'=>$this->getUser(),'time'=>$time,'filename'=>$name];
        $data = json_decode(HttpExtend::post($this->prefix.'/info', $time), true);
        return $data['code'] == 1? $data['data']: [];
    }

    /**
     * 获取文件上传地址
     * @return string
     */
    public function upload(): string
    {
        return "{$this->prefix}/api/add";
    }
    
    /**
     * 获取签名
     */
    public function getSign($time){
        $api_user = $this->getUser();
        $public_key = sysconf('storage.ipfs_public_key') ?: '';
        return md5($api_user.'#'.$time.'#'.$public_key);
    }
    
    /**
     * 获取Script
     */
    public function getUser(){
        return sysconf('storage.ipfs_api_user') ?: '';
    }
}