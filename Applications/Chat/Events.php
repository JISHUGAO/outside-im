<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose 
 */
use \GatewayWorker\Lib\Gateway;

class Events
{
    const DEFAULT_ROOM = 1;
   /**
    * 有消息时
    * @param int $client_id
    * @param mixed $message
    */
   public static function onMessage($client_id, $message)
   {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if (!$message_data) {
            return ;
        }

        //对应web端的逻辑
        if (isset($message_data['client']) && $message_data['client'] === 'web') {
            self::webLogic($client_id, $message_data);
            return;
        }

   }

    /**
     * 对应web端逻辑
     * @param $client_id
     * @param $message_data
     */
   public static function webLogic($client_id, $message_data)
   {
       $room_id = self::DEFAULT_ROOM;
       switch ($message_data['type']) {
           case 'login':
               $user = $message_data['data'];
               if (!empty($user) && isset($user['id'])) {
                   Gateway::bindUid($client_id, $user['id']);
                   $_SESSION = $user;
                   Gateway::setSession($client_id, $user);

                   //加入聊天室
                   Gateway::joinGroup($client_id, $room_id);
                   //给其他用户发送当前用户的登录状态
                   $data2 = [
                       'type' => 'login',
                       'user' => $user
                   ];
                   Gateway::sendToGroup($room_id, json_encode($data2));
               } else {
                   Gateway::closeCurrentClient();
               }
               break;
           case 'list':
                //当前用户获取在线用户列表
               $client_list = Gateway::getClientSessionsByGroup($room_id);
               $data = [
                   'type' => 'list',
                   'client_list' => array_values($client_list)
               ];
               Gateway::sendToCurrentClient(json_encode($data));
               break;
           case 'say':
                $_SESSION = $user = Gateway::getSession($client_id);
                if (!isset($message_data['content']) || $message_data['content'] == '') {
                    return;
                }
                $user['content'] = $message_data['content'];
                
                $data = [
                    'type' => 'say',
                    'user' => $user,
                    'timestamp' => time()
                ];
                Gateway::sendToGroup($room_id, json_encode($data));
               break;
           case 'logout':

               break;
       }
       return;
   }
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id)
   {
       // debug
       echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";

       $room_id = self::DEFAULT_ROOM;
       $new_message = array(
           'type' => 'logout',
           'user' => $_SESSION
       );

       Gateway::sendToGroup($room_id, json_encode($new_message));
   }
  
}
