<?php
/**
 *.                                         . . ...
 *                                                  2B小姐姐啊！请斩断所有BUG:)
 *   :.               .vv:                    . ... User:Apa琦
 *   UB:            ..LUPd1             .rq.     .  Date:2021/1/9
 *    :Qr           vUUgBIQ.            iQDK        Time:14:35
 *      ZP          rvJX1ZP              jPR:       Created by PhpStorm.
 *       vQ.       .7.rQb                 JBM
 *        .Rj     .BBBBBQr:v              iBJSr
 *          BBui  :BBBBQBBQQB             MY  B.  .
 *           BQB  :BQQBBQBBBQ            .7   .  ..
 *          . 7Zg  BB vBBQQBB                   ...
 *   . .       URQ.BB   QBMQBs               .......
 *. ........   rBPBQBi .BQQRBQQdr           .....:i:
 *. ..........  .BB2Sr7BBBRQMBQBg            ....ii:
 *. ...........   X:iSBBRRQRQMQQBBL         . . :ii:
 *..............   JbBBE7MQQMRgRQBBB.      ... .iv7r
 *:.:.:........   :BBBMir2gQQgMMRgMBP       ..i7YYj7
 *:..:::.:.:...  vBBBBJivXdRDMgQMgQBBQ     . :77vvv7
 *:.:.:::.:.:.. qBBBBQRMBBBgBMMQBBQDQQS    ..::i77vr
 *..::.:::.:.. QQBBBQBBBQBBPBQRBX5JLi.     ..:irr77r
 *:.:::::::::.:BBBBPBMPQBBIvBBBQ           ..:iirr7r
 *:.::::::::::..7KEIP.rBBB   BBBr          ..:iiirri
 *:.:.:::.:::.:.      EBBi   jBBrEgr      ...:iiiir:
 *:.:i:i:i::::::.:.. rBBZ    dQB  :dRU.   ...:ii:ii:
 *i.iiiii::.:::.:::..BBB:    RBB     vgEJ:  :::.:.:.
 *i:ii:::::::i::.:...BBB     IQB       .755sii.....
 *i.i:i:::rirrri:::..BBZ ... .BB    ......:r7::....
 *JvLYYu7ri77777rrii.EBX.r77i.QB:.::::.:::::.
 *RgQQBQgU7:ir77sj21sEBqrr7r7.PBr:iii:::iirri.
 *QQQQQQQQgPuuYYv77YrIBPrvr:. 7Br :::::iiriiiYjYriii
 *RgMMRgQQQQQggdPUUjuIBDXPgSLrDBQrr777777vLr7suJjsUJ
 *MggRgMgMgQQQQBBBBBBBBBQBBBBBBBBBEggDZEPdZMMgEEEZEZ
 */

namespace App\Http\Controllers;


use App\Tool\Lunar;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class Index
{
    private $client;
    private $lunar;

    public function __construct(Client $client,Lunar $lunar)
    {
        $this->client=$client;
        $this->lunar=$lunar;
    }


    public function index(Request $request){
        $data=json_decode($request->getContent(),true);
        $month=$data['month'];

        //查看是否是日期格式
        $monthTime=strtotime($month);
        if($monthTime==false){
            return ['code'=>500,'msg'=>'时间格式不正确','data'=>[]];
        }

        $month=date("Y-m",$monthTime);

        $days=DB::table('month as a')
            ->join('day as b','a.month_id','=','b.month_id')
            ->where('a.month',$month)
            ->get([
                'day','lunar_month','is_working'
            ])->toArray();

        if(!$days){
            //获取工作日信息
            $response=$this->client->post('http://Tool.bitefu.net/jiari/',[
                'query'=>['d'=>$month],
                'timeout' => 3
            ]);
            $working=(string)$response->getBody();
            $working=json_decode($working,true);
            $working=$working[date("Ym",strtotime($month))];

            //循环处理日期数据
            $period = CarbonPeriod::create(
                $month.'-01',
                Carbon::parse($month.'-01')->lastOfMonth()->format('Y-m-d')
            );

            $monthId=DB::table('month')->insertGetId([
                'month'=>$month,
                'time'=>time()
            ]);
            $list=[];
            $days=[];
            foreach ($period as $v) {
                $day=[];
                //判断日期是否是工作日
                $md=$v->format('md');
                if (isset($working[$md])){
                    $day['is_working']=($working[$md]==0)?1:0;
                }else{
                    $day['is_working']=($v->dayOfWeek==0||$v->dayOfWeek==6)?0:1;
                }

                //获取某个日期的农历
                $lunar=$this->lunar->convertSolarToLunar(
                    $v->format('Y'),$v->format('m'),$v->format('d')
                );
                $day['lunar_month']=$lunar[1].$lunar[2];

                $day['month_id']=$monthId;
                $day['day']=(int)$v->format('d');
                $day['time']=time();
                $list[]=$day;
                $days[]=[
                    'day'=>$day['day'],
                    'lunar_month'=>$day['lunar_month'],
                    'is_working'=>$day['is_working']
                ];
            }

            DB::table('day')->insert($list);
        }

        return ['code'=>0,'msg'=>'','data'=>$days];
    }

    private function getIsWorking(string $month){
        try {

        }catch (\Exception $e){
            Log::error('请求是否工作日异常'.$e->getMessage());
            $dayType=1;
        }
    }

}
