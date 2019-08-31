<?php
/*
{
    "require": {
        "aws/aws-sdk-php": "^3.55"
    }
}
*/
header("Access-Control-Allow-Origin: *");
require_once __DIR__."/vendor/autoload.php";

function aws_key(){
	return 'AKIAJTWIFZKPJAX5AMSQ';
}
function aws_secret(){
	return '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';
}
function bucket() {
	return "oupbucket";
}
/**
 * The key perfix in the bucket to put all uploads in
 * @return string
 */
function prefix() {
    return "upload/";
}
/**
 * Easy wrapper around S3 API
 * @param  string $command the function to call
 * @param  mixed $args    variable args to pass
 * @return mixed
 */
function s3($command=null,$args=null)
{
	static $s3=null;
	if ($s3===null)
	$s3 = new Aws\S3\S3Client([
	    'version' => 'latest',
	    'region'  => 'ap-south-1',
	    'signature_version' => 'v4',
	        'credentials' => [
	        'key'    => aws_key(),
	        'secret' => aws_secret(),
	    ]
	]);
	if ($command===null)
		return $s3;
	$args=func_get_args();
	array_shift($args);
	try {
		$res=call_user_func_array([$s3,$command],$args);
		return $res;
	}
	catch (AwsException $e)
	{
		echo $e->getMessage(),PHP_EOL;
	}	
	return null;
}

function json_output($data)
{
    header('Content-Type: application/json');
    die(json_encode($data));
}

function abortPendingUploads($bucket)
{
    $count=0;
    $res=s3("listMultipartUploads",["Bucket"=>bucket()]);
    if (is_array($res["Uploads"]))
    foreach ($res["Uploads"] as $item)
    {

        $r=s3("abortMultipartUpload",[
            "Bucket"=>$bucket,
            "Key"=>$item["Key"],
            "UploadId"=>$item["UploadId"],
        ]);
        $count++;
    }
    return $count;
}

function setCORS($bucket)
{
    $res=s3("getBucketCors",["Bucket"=>$bucket]);
    $res=s3("putBucketCors",
        [
            "Bucket"=>$bucket,
            "CORSConfiguration"=>[
                "CORSRules"=>[
                    [
                    'AllowedHeaders'=>['*'],
                    'AllowedMethods'=> ['POST','GET','HEAD','PUT'],
                    "AllowedOrigins"=>["localhost","*"],
                    ],
                ],
            ],
        ]);
}

if (isset($_POST['command']))
{
	$command=$_POST['command'];
	if ($command=="create")
	{
		$res=s3("createMultipartUpload",[
			'Bucket' => bucket(),
            'Key' => prefix().$_POST['fileInfo']['name'],
            'ContentType' => $_REQUEST['fileInfo']['type'],
            'Metadata' => $_REQUEST['fileInfo']
		]);
	 	json_output(array(
               'uploadId' => $res->get('UploadId'),
                'key' => $res->get('Key'),
        ));
	}

	if ($command=="part")
	{
		$command=s3("getCommand","UploadPart",[
			'Bucket' => bucket(),
            'Key' => $_REQUEST['sendBackData']['key'],
            'UploadId' => $_REQUEST['sendBackData']['uploadId'],
            'PartNumber' => $_REQUEST['partNumber'],
            'ContentLength' => $_REQUEST['contentLength']
		]);

        // Give it at least 24 hours for large uploads
		$request=s3("createPresignedRequest",$command,"+48 hours");
        json_output([
            'url' => (string)$request->getUri(),
        ]);		
	}

	if ($command=="complete")
	{
	 	$partsModel = s3("listParts",[
            'Bucket' => bucket(),
            'Key' => $_REQUEST['sendBackData']['key'],
            'UploadId' => $_REQUEST['sendBackData']['uploadId'],
        ]);
        $model = s3("completeMultipartUpload",[
            'Bucket' => bucket(),
            'Key' => $_REQUEST['sendBackData']['key'],
            'UploadId' => $_REQUEST['sendBackData']['uploadId'],
            'MultipartUpload' => [
            	"Parts"=>$partsModel["Parts"],
            ],
        ]);
        json_output([
            'success' => true
        ]);
	}
	if ($command=="abort")
	{
		 $model = s3("abortMultipartUpload",[
            'Bucket' => bucket(),
            'Key' => $_REQUEST['sendBackData']['key'],
            'UploadId' => $_REQUEST['sendBackData']['uploadId']
        ]);
        json_output([
            'success' => true
        ]);
	}

	exit(0);
}

include "page.html";