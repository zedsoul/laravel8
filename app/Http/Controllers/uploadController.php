<?php

namespace App\Http\Controllers;



use App\services\OSS;
use Illuminate\Http\Request;

use OSS\OssClient;
use OSS\Core\OssException;
use OSS\Core\OssUtil;
class uploadController extends Controller
{
    /****
     *oss分片上传
     * @param Request $request
     *
     */
    public function load(Request $request){
        // $login_id = auth('api')->user()->id;
        $uploadFile = $request['file'];
        // 阿里云主账号AccessKey拥有所有API的访问权限，风险很高。强烈建议您创建并使用RAM账号进行API访问或日常运维，请登录RAM控制台创建RAM账号。
        $accessKeyId = "";
        $accessKeySecret = "";
        // Endpoint以杭州为例，其它Region请按实际情况填写。
        $endpoint = "";
        $bucket= "";
        $fileName = rand(1000,9999) . $uploadFile->getFilename() . time() .date('ymd') . '.' . $uploadFile->getClientOriginalExtension();
        $object = date('Y-m/d').'/'.$fileName;

        /**
         *  步骤1：初始化一个分片上传事件，获取uploadId。
         */
        try{
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            //返回uploadId。uploadId是分片上传事件的唯一标识，您可以根据uploadId发起相关的操作，如取消分片上传、查询分片上传等。
            $uploadId = $ossClient->initiateMultipartUpload($bucket, $object);
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": initiateMultipartUpload FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        print(".....初始化分片事件成功,"."uploadId为:". $uploadId."\n");
        /*
         * 步骤2：上传分片。
         */
        $partSize =4 * 1024 * 1024;
        $uploadFileSize = filesize($uploadFile);
        $pieces = $ossClient->generateMultiuploadParts($uploadFileSize, $partSize);
        $responseUploadPart = array();
        $uploadPosition = 0;
        $isCheckMd5 = true;
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (integer)$piece[$ossClient::OSS_SEEK_TO];
            $toPos = (integer)$piece[$ossClient::OSS_LENGTH] + $fromPos - 1;
            $upOptions = array(
                // 上传文件。
                $ossClient::OSS_FILE_UPLOAD => $uploadFile,
                // 设置分片号。
                $ossClient::OSS_PART_NUM => ($i+1),
                // 指定分片上传起始位置。
                $ossClient::OSS_SEEK_TO => $fromPos,
                // 指定文件长度。
                $ossClient::OSS_LENGTH => $toPos - $fromPos + 1,
                // 是否开启MD5校验，true为开启。
                $ossClient::OSS_CHECK_MD5 => $isCheckMd5,
            );
            // 开启MD5校验。
            if ($isCheckMd5) {
                $contentMd5 = OssUtil::getMd5SumForFile($uploadFile, $fromPos, $toPos);
                $upOptions[$ossClient::OSS_CONTENT_MD5] = $contentMd5;
            }
            try {
                // 上传分片。
                $res=  $responseUploadPart[] = $ossClient->uploadPart($bucket, $object, $uploadId, $upOptions);
            } catch(OssException $e) {
                printf(__FUNCTION__ . ": initiateMultipartUpload, uploadPart - part#{$i} FAILED\n");
                printf($e->getMessage() . "\n");
                return false;
            }
            printf( "第{$i}部分 加载成功\n");
        }
// $uploadParts是由每个分片的ETag和分片号（PartNumber）组成的数组。
        $uploadParts = array();
        foreach ($responseUploadPart as $i => $eTag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $eTag,
            );
        }
        /**
         * 步骤3：完成上传。
         */
        try {
            // 执行completeMultipartUpload操作时，需要提供所有有效的$uploadParts。OSS收到提交的$uploadParts后，会逐一验证每个分片的有效性。当所有的数据分片验证通过后，OSS将把这些分片组合成一个完整的文件。
            $ossClient->completeMultipartUpload($bucket, $object, $uploadId, $uploadParts);
        }  catch(OssException $e) {
            printf(__FUNCTION__ . ": completeMultipartUpload FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        printf( "分片合并成功，已完成上传\n");
        /**
         * 步骤4:获取url
         */
        try {
            $Url = OSS::getPublicObjectURL('testfile01',$object);
            return $Url?
                json_success('上传成功!',$Url,  200):
                json_fail('上传失败',null, 100 );
        }
        catch(OssException $e) {
            printf(__FUNCTION__ . ": completeMultipartUpload FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }

    }
    public function upload(Request $request)
    {
        //获取上传的文件
        $file = $request->file('file');
        //获取上传图片的临时地址
        $a=exif_imagetype($file);
        $tmppath = $file->getRealPath();   //C:\phpEnv\temp\php\php6E26.tmp
        //生成文件名
        $fileName = rand(1000,9999) . $file->getFilename() . time() .date('ymd') . '.' . $file->getClientOriginalExtension();
        //拼接上传的文件夹路径(按照日期格式1810/17/xxxx.jpg)
        $pathName = date('Y-m/d').'/'.$fileName;
        //上传图片到阿里云OSS
        OSS::publicUpload('testfile01', $pathName, $tmppath, ['ContentType' => $file->getClientMimeType()]);
        //获取上传图片的Url链接
        $Url = OSS::getPublicObjectURL('testfile01',$pathName);
        // 返回状态给前端，Laravel框架会将数组转成JSON格式
        return ['code' => 0, 'msg' => '上传成功', 'data' => ['src' => $Url]];
    }
}
