<?php
require __DIR__ . '/vendor/autoload.php';
set_time_limit(3600);
ini_set('date.timezone','Asia/Shanghai'); 
//require('/root/vendor/autoload.php');
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

$vfilename = $argv[1]; 

if (PHP_SAPI !== 'cli') {
    	die ("CLI版本，只能在CLI环境运行!");
	} 
else {
	$v = new Dianyingjianjie();
	$v->set_log_file('video_convert.txt');
	$v->set_vfilename($vfilename);
	$v->run();
}

class Dianyingjianjie{
	function run() {
		$this->get_file_extension($this->vfilename);
		$this->get_file_name($this->vfilename);
		$this->log('主程序开始运行');
		$video_str_ori = $this->CutYoutubeVideo($this->vfilename);
		$video_str=array_splice($video_str_ori,1);
		print_r($video_str);
		$this->MergeYoutubeVideo($video_str);
		//$this->ResizeandM($this->vid);
		$movieintro = $this->get_movie_intro($this->file_name);
		while(strlen($movieintro)<30){
			$this->log('简介获取失败，再次尝试');
			$movieintro = $this->get_movie_intro($this->file_name);
			}
		$peiyin = $this->get_ali_peiyin($movieintro);
		$this->adj_movie_volume($this->newFile);
		$this->peiyin($this->movie_out_name_volume,$peiyin);
		}


	function ResizeandM($vfilename){
		$this->log("Start to reszie  MP4 file!");
		$mvideopath = "/var/www/html/".$this->vfilename."_1m.mkv"; 
		$ffmpeg = FFMpeg\FFMpeg::create(array(
    			'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
    			'ffprobe.binaries' => '/usr/bin/ffprobe',
    			'timeout'          => 3600, // The timeout for the underlying process
			), $logger);
		$video = $ffmpeg->open($mvideopath);
		$video->filters()->crop(new FFMpeg\Coordinate\Point(160, 80, false), new FFMpeg\Coordinate\Dimension(960, 540));
		$video->save(new FFMpeg\Format\Video\X264(), '/var/www/html/'.$this->vfilename.'_1mf.mkv');
		}


	function MergeYoutubeVideo($video_str){
		$this->log("将切分的影片合成为一个新的影片!");
		$newFile ="/var/www/html/".$this->vfilename."_1m.".$this->file_extension; 
		$ffmpeg = FFMpeg\FFMpeg::create(array(
    			'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
    			'ffprobe.binaries' => '/usr/bin/ffprobe',
   			 'timeout'          => 3600, // The timeout for the underlying process
			), $logger);
		$video = $ffmpeg->open($video_str[0]);
		print_r($video_str);
		$format = new FFMpeg\Format\Video\X264();
		$video->concat($video_str)->saveFromDifferentCodecs($format, $newFile);
		$this->newFile = $newFile;
		$this->log("合并后短片名：".$this->newFile);
		}

	function get_movie_intro($moviename){
		$url = 'https://www.douban.com/search?q='.urlencode($moviename).'&cat=1002';
		$this->log("尝试从豆瓣获取影片信息：".$url);
		$response = $this->curlPost($url,"");
		$result = $this->get_tag_data($response,"div","class","result");
		preg_match('/href="(.*?)"/i',$result[0],$match);
		$url2 = $match[1];
		$this->log("影片信息跳转地址：".$url2);
		$response2 = $this->curlPost($url2,"");
		preg_match('/redirected you to(.*?)(\d+)/i',$response2,$match2);
		$url3 = trim($match2[1].$match2[2]."/");
		$this->log("尝试从跳转地址获取影片信息：".$url3);
		$response3 = $this->curlGet($url3);
		$result3 = $this->get_tag_data($response3,"div","class","indent");
		$intro = $result3[0];
		//print_r($result3[0]);
		$intro = preg_replace('~<([a-z]+?)\s+?.*?>~i','<$1>',$intro); //删除html标签
		$intro = strip_tags($intro);
		$intro = trim($intro);
		//preg_match_all('/　　(.*)。/i',$intro,$match3);
		//print_r($match3);
		//$intro = trim($match3[0][0]);
		//$intro = preg_replace("/[a-z,A-Z]/","",$intro);
		$intro = preg_replace('/\(.*?\)/', '', $intro);//删除括号及括号内的内容
		$intro = preg_replace('/\（.*?\）/', '', $intro);//删除中文括号及括号内的内容
		$intro = preg_replace('/ /', '', $intro);//替换空格
		$intro = preg_replace('/&copy;豆瓣/', '', $intro);
		$intro = preg_replace("/s/", '', $intro);
		$intro = preg_replace('/　　/', '', $intro);//替换TAB
		$intro = "电影  ".$this->file_name." 简介： ".$intro;
		$this->log("影片简介：".$intro);
		return $intro;
		}

	function get_ali_peiyin($content) {
		$AccessKeyID ="ACSQdRVyUt2BQryo";
		$AccessKeySecret = "CsfAK8yUeS";		
		//使用你的AccessKey ID和Access Key Secret初始化
		AlibabaCloud::accessKeyClient($AccessKeyID, $AccessKeySecret)->regionId("cn-shanghai")->asDefaultClient();

		//获取token等初始化的值
		    $rpcResult = AlibabaCloud::rpc()
                             ->host('nls-meta.cn-shanghai.aliyuncs.com')
                             //->domain('nls-meta.cn-shanghai.aliyuncs.com')
                             ->regionId('cn-shanghai')
                             ->version('2019-02-28')
                             ->action('CreateToken')
                             ->method('POST')
                             ->connectTimeout(3) // 设置连接超时10毫秒，当单位小于1，则自动转换为毫秒
                             ->timeout(3) // 设置超时10毫秒，当单位小于1，则自动转换为毫秒
                             ->debug(true) // 开启调试CLI下会输出详细信息
                             ->request();
		//print_r($rpcResult);
		$token = $rpcResult['Token']['Id'];//获取token值：
		$this->log("阿里TTS token：".$token);
		//Get方式获取
		$this->log("将简介转发为语音");
		//https://nls-gateway.cn-shanghai.aliyuncs.com/stream/v1/tts?appkey=trUDdnGgzS68wobG&token=a597b4ed9af6491da227200f353fb44d&text=%E4%BB%8A%E5%A4%A9%E6%98%AF%E5%91%A8%E4%B8%80%EF%BC%8C%E5%A4%A9%E6%B0%94%E6%8C%BA%E5%A5%BD%E7%9A%84%E3%80%82&format=wav&sample_rate=16000
		//$token = "7c02d0c5b31a4460847165c1637e1d89";
		$appkey = "trUDdnGgzS68wobG";
		$text = urlencode($content);
		$voice = "Aicheng";
		$volume = 100;
		$voiceurl = "https://nls-gateway.cn-shanghai.aliyuncs.com/stream/v1/tts?appkey=".$appkey."&token=".$token."&text=".$text."&voice=".$voice."&format=mp3&sample_rate=16000"."&volume=".$volume;
		$this->log("语音地址：".$voiceurl);
		$peiyin_file = "/var/www/html/".$this->file_name."配音.mp3";
		$this->log("将语音文件保存到MP3：".$peiyin_file);
		$this->DownCurl($voiceurl,$peiyin_file);
		$peiyin_dur_shell = "/usr/bin/ffmpeg -i ".$peiyin_file." 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
		$peiyin_dur_ori = shell_exec($peiyin_dur_shell);
  		$parsed = date_parse($peiyin_dur_ori);
  		$this->peiyin_dur = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'];
		$this->log("配音长度：" .$this->peiyin_dur);
		return $peiyin_file;
		}

	function adj_movie_volume($movie_file_name){
		$this->movie_out_name_volume = "/var/www/html/".$this->file_name."音量调整版.".$this->file_extension;
		$adj_movie_volume_shell = "/usr/bin/ffmpeg -i ".$movie_file_name." -af \"volume='if(lt(t,$this->peiyin_dur),0,min(0+(t-$this->peiyin_dur)/3*(1),1))':eval=frame\" $this->movie_out_name_volume";
		//print($adj_movie_volume_shell);
		$this->log("调整音量命令：" .$adj_movie_volume_shell);
		shell_exec($adj_movie_volume_shell);		
		}

	function peiyin($moviename,$peiyinmp3name){
		$movie_out_name = "/var/www/html/".$this->file_name."_简介配音版.".$this->file_extension;
		$shell = "/usr/bin/ffmpeg -i ".$moviename."  -i  ".$peiyinmp3name." -c:v copy -c:a aac -strict -2 -filter_complex '[0:a][1:a] amix=inputs=2:duration=longest' ".$movie_out_name;
		//print($shell);
		$this->log("开始给视频配音：".$shell);
		shell_exec($shell);
		$this->log("配音完成，最终视频文件：".$movie_out_name);
		}


	function CutYoutubeVideo($vfilename){
	$path = [
   		 'ffmpeg.binaries'  => '/usr/bin/avconv',
   		 'ffmpeg.binaries' => '/usr/bin/ffmpeg',
   		 'ffprobe.binaries' => '/usr/bin/avprobe',
   		 'ffprobe.binaries' => '/usr/bin/ffprobe',
		];
	$ffprobe = FFMpeg\FFProbe::create(array(
   		 	'ffmpeg.binaries'  => '/usr/bin/avconv',
   			 'ffmpeg.binaries' => '/usr/bin/ffmpeg',
   		 	'ffprobe.binaries' => '/usr/bin/avprobe',
   		 	'ffprobe.binaries' => '/usr/bin/ffprobe',
   			 'timeout'          => 3600, // The timeout for the underlying process
			), $logger);	
	//$ffprobe = FFMpeg\FFProbe::create($path);
	$videoInfo = $ffprobe->format($vfilename);
	$duration = $ffprobe->format($vfilename)->get('duration',100);
	$number_str_ori = $this->getDivideNumberT($duration, 4, 0);
	$number_str=array_splice($number_str_ori,1);
	//print_r($number_str);
	$this->log("从原始影片文件切取10个小影片!");
	foreach ($number_str as $number) {
		//echo($number);
		$cutedfilename=$vfilename.'_new_'.$number.".".$this->file_extension;
		$this->log("正在切片：".$cutedfilename);
		$ffmpeg = FFMpeg\FFMpeg::create(array(
   		 	'ffmpeg.binaries'  => '/usr/bin/avconv',
   			 'ffmpeg.binaries' => '/usr/bin/ffmpeg',
   		 	'ffprobe.binaries' => '/usr/bin/avprobe',
   		 	'ffprobe.binaries' => '/usr/bin/ffprobe',
   			 'timeout'          => 3600, // The timeout for the underlying process
			), $logger);	
		$video = $ffmpeg->open($vfilename);
		$video->filters()->clip(FFMpeg\Coordinate\TimeCode::fromSeconds($number),FFMpeg\Coordinate\TimeCode::fromSeconds(60));
		$video->save(new FFMpeg\Format\Video\X264(), $cutedfilename);
		$filename_str = $filename_str."+".$cutedfilename;
		}
		return explode('+', $filename_str);
	}

	function get_file_name ($filename) {
		$this->file_name  = basename($filename,".".$this->file_extension);;
		$this->log("影片名为: " . $this->file_name );
		}

	function get_file_extension($filename) {
		$this->file_extension = pathinfo($filename,PATHINFO_EXTENSION);
		$this->log("影片格式后缀为: " . $this->file_extension);
		}

	function set_log_file($filename) {
		$this->log_file = $filename;
		$this->log("设置log文集为: " . $filename);
		}


	function set_vfilename($vfilename) {
        	$this->vfilename = $vfilename; 
        	$this->log("影片文件名: " . $vfilename);
    	} 

	function getDivideNumberT($number, $total, $index = 2) {
		$number_str ="0";
		// 除法取平均数
		$divide_number  = bcdiv($number, $total, $index);
		for ($x=1; $x<$total; $x++) {
			$number = $divide_number*$x;
			//echo "数字是：$number /r/n";
			$number_str = $number_str."+".$number;
		}
		return explode('+', $number_str);
	}
	function get_tag_data($html,$tag,$class,$value){ 
   		 //$value 为空，则获取class=$class的所有内容
    		$regex = $value ? "/<$tag.*?$class=\"$value\".*?>(.*?)<\/$tag>/is" :  "/<$tag.*?$class=\".*?$value.*?\".*?>(.*?)<\/$tag>/is";
    		preg_match_all($regex,$html,$matches,PREG_PATTERN_ORDER); 
    		return $matches[1];//返回值为数组 ,查找到的标签内的内容
		}


	function curlPost($url,$data)
    	{
		$user_agent = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET4.0C; .NET4.0E)";
		$ch = curl_init();
		$params[CURLOPT_URL] = $url;    //请求url地址
		$params[CURLOPT_HEADER] = true; //是否返回响应头信息 CURLINFO_HEADER_OUT, TRUE
		$params[CURLINFO_HEADER_OUT] = true; 
		$params[CURLOPT_SSL_VERIFYPEER] = false;
		$params[CURLOPT_USERAGENT] = $user_agent;
		$params[CURLOPT_SSL_VERIFYHOST] = false;
		$params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
		$params[CURLOPT_POST] = true;
		$params[CURLOPT_POSTFIELDS] = $data;
		curl_setopt_array($ch, $params); //传入curl参数
		$content = curl_exec($ch); //执行
		$header=curl_getinfo($ch);
		curl_close($ch); //关闭连接
		return $content;
 		}

	function curlGet($url)
    	{
		$user_agent = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET4.0C; .NET4.0E)";
		$ch = curl_init();
		$params[CURLOPT_URL] = $url;    //请求url地址
		$params[CURLOPT_HEADER] = true; //是否返回响应头信息 CURLINFO_HEADER_OUT, TRUE
		$params[CURLINFO_HEADER_OUT] = true; 
		$params[CURLOPT_SSL_VERIFYPEER] = false;
		$params[CURLOPT_USERAGENT] = $user_agent;
		$params[CURLOPT_SSL_VERIFYHOST] = false;
		$params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
		curl_setopt_array($ch, $params); //传入curl参数
		$content = curl_exec($ch); //执行
		$header=curl_getinfo($ch);
		curl_close($ch); //关闭连接
		return $content;
 		}

	function DownCurl($url,$filePath){
		//初始化
		$curl = curl_init();
		//设置抓取的url
		curl_setopt($curl, CURLOPT_URL, $url);
		//打开文件描述符
		$fp = fopen ($filePath, 'w+');
		curl_setopt($curl, CURLOPT_FILE, $fp);
		//这个选项是意思是跳转，如果你访问的页面跳转到另一个页面，也会模拟访问。
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl,CURLOPT_TIMEOUT,50);
		curl_setopt($curl, CURLOPT_NOPROGRESS, 0);
		//执行命令
		curl_exec($curl);
		//关闭URL请求
		curl_close($curl);
		//关闭文件描述符
		fclose($fp);
		}

	function log($log_line) {
        	$time_array = explode(" ", microtime());
        	$time_array[0] = sprintf('%.6f', $time_array[0]);
       		$time = date('Y/m/d H:i:s.', $time_array[1]) . substr($time_array[0], 2) ;
        	if(!empty($this->log_file)) {
			file_put_contents($this->log_file, "[$time] $log_line" . PHP_EOL, FILE_APPEND | LOCK_EX);
        		}
		echo "[$time] $log_line" . PHP_EOL;
	}



}
