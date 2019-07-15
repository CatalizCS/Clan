<?php

namespace Clan;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class ClanCommands {

    public $plugin;

    public function __construct(ClanMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player || ($sender->isOp() && $this->plugin->prefs->get("AllowOpToChangeFactionPower"))) {
            $prefix = $this->plugin->prefs->get("prefix");
            if (empty($args)){
                $sender->sendMessage($this->plugin->formatMessage("$prefix Hãy nhập /clan help để biết chi tiết tất cả lệnh."));
                return true;
            }
            if (strtolower($args[0]) == "addpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan addpower <Clan name> <power>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->addFactionPower($args[1], $args[2]);
                            $sender->sendMessage($this->plugin->formatMessage("Năng lượng " . $args[2] . " Đã thêm vào Faction " . $args[1]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Clan " . $args[1] . " Không tồn tại."));
                }
            }
            if (strtolower($args[0]) == "setpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan setpower <clan name> <power>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->setFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage("Clan " . $args[1] . " Đặt năng lượng " . $args[2]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Clan " . $args[1] . " Không tồn tại"));
                }
            }
            if (!$sender instanceof Player) return true;
        }
        $playerName = $sender->getPlayer()->getName();
        if (strtolower($command->getName()) === "clan") {
          $prefix = $this->plugin->prefs->get("prefix");
            if (empty($args)) {
                $sender->sendMessage($this->plugin->formatMessage("$prefix Hãy nhập /clan help để biết chi tiết tất cả lệnh."));
                return true;
            }

            ///////////////////////////////// WAR /////////////////////////////////

            if ($args[0] == "war") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử Dụng: /clan war <Clan name:tp>"));
                    return true;
                }
                if (strtolower($args[1]) == "tp") {
                    foreach ($this->plugin->wars as $r => $f) {
                        $fac = $this->plugin->getPlayerFaction($playerName);
                        if ($r == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$f][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                        if ($f == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$r][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                    }
                    $sender->sendMessage("$prefix Bạn phải trong đấu trường mới có thể làm việc đó");
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn chỉ có thể sử dụng Chữ và Số"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan không tồn tại."));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới có thể làm việc này"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Chỉ có leader Clan bạn mới có thể tổ chức chiến tranh."));
                    return true;
                }
                if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn không phải là đối thủ của $args[1]"));
                    return true;
                } else {
                    $factionName = $args[1];
                    $sFaction = $this->plugin->getPlayerFaction($playerName);
                    foreach ($this->plugin->war_req as $r => $f) {
                        if ($r == $args[1] && $f == $sFaction) {
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                $task = new ClanWar($this->plugin, $r);
                                $handler = $this->plugin->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                $task->setHandler($handler);
                                $p->sendMessage("$prefix Chiến tranh Giữa $factionName Và $sFaction Vừa bắt đầu!");
                                if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                    $this->plugin->war_players[$sFaction][] = $p->getName();
                                }
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    $this->plugin->war_players[$factionName][] = $p->getName();
                                }
                            }
                            $this->plugin->wars[$factionName] = $sFaction;
                            unset($this->plugin->war_req[strtolower($args[1])]);
                            return true;
                        }
                    }
                    $this->plugin->war_req[$sFaction] = $factionName;
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                            if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                $p->sendMessage("$sFaction Muốn bắt đầu chiến tranh, '/clan war $sFaction' Để bắt đầu!");
                                $sender->sendMessage("Clan vừa gửi đơn chấp thuận.");
                                return true;
                            }
                        }
                    }
                    $sender->sendMessage("$prefix Leader Clan của bạn không online.");
                    return true;
                }
            }

            /////////////////////////////// CREATE ///////////////////////////////

            if ($args[0] == "create") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan create <Clan name>"));
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn chỉ có thể dùng Chữ hoặc Số."));
                    return true;
                }
                if ($this->plugin->isNameBanned($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Tên clan này không được cho phép tạo."));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Tên Clan này đã có người sở hữu"));
                    return true;
                }
                if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Tên clan quá dài, vui lòng thử lại tên khác."));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải rời Clan trước để thực hiện lệnh này."));
                    return true;
                } else {
                    $factionName = $args[1];
                    $rank = "Leader";
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", $playerName);
                    $stmt->bindValue(":faction", $factionName);
                    $stmt->bindValue(":rank", $rank);
                    $result = $stmt->execute();
                    $this->plugin->updateAllies($factionName);
                    $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                    $this->plugin->updateTag($sender->getName());
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã được tạo, Xin chúc mừng!", true));
                    return true;
                }
            }

            /////////////////////////////// INVITE ///////////////////////////////

            if ($args[0] == "invite") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan invite <Tên người chơi>"));
                    return true;
                }
                if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName))) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan đã đầy, vui lòng kick bớt để thực hiện lệnh này!"));
                    return true;
                }
                $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                if (!($invited instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi không online"));
                    return true;
                }
                if ($this->plugin->isInFaction($invited->getName()) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi này đã ở trong một clan khác!"));
                    return true;
                }
                if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                    if (!($this->plugin->isOfficer($playerName) || $this->plugin->isLeader($playerName))) {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Chỉ có leader, officers mới có thể mời!"));
                        return true;
                    }
                }
                if ($invited->getName() == $playerName) {

                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể tự mời bản thân vào clan của chính mình!"));
                    return true;
                }

                $factionName = $this->plugin->getPlayerFaction($playerName);
                $invitedName = $invited->getName();
                $rank = "Member";

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                $stmt->bindValue(":player", $invitedName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":invitedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$invitedName Đã được mời", true));
                $invited->sendMessage($this->plugin->formatMessage("$prefix Bạn được mời vào Clan $factionName. Nhập '/clan accept hoặc /clan deny' vào chat để đồng ý hoặc từ chối lời mời!", true));
            }

            /////////////////////////////// LEADER ///////////////////////////////

            if ($args[0] == "leader") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan leader <Tên người chơi>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải trong Clan mới có thể thực hiện lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Vui lòng thêm người chơi vào clan trước"));
                    return true;
                }
                if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi không online"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {

                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể trao quyền leader cho bản thân bạn."));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $playerName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Leader");
                $result = $stmt->execute();


                $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không còn là Leader ở clan này", true));
                $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("$prefix Bạn là Leader của clan \n $factionName!", true));
                $this->plugin->updateTag($sender->getName());
                $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
            }

            /////////////////////////////// PROMOTE ///////////////////////////////

            if ($args[0] == "promote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan promote <Tên người chơi>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải trong Clan mới thực hiện được lệnh này"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi này không online trong Clan"));
                    return true;
                }
                $promotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                if ($promotee instanceof Player && $promotee->getName() == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể thăng chức cho chính bản thân bạn."));
                    return true;
                }

                if ($this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi này đã là Officer"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Officer");
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("Người chơi $args[1] Được thăng chức lên làm Officer Clan", true));

                if ($promotee instanceof Player) {
                    $promotee->sendMessage($this->plugin->formatMessage("$prefix Bạn đã được thăng chức lên officer ở clan $factionName!", true));
                    $this->plugin->updateTag($promotee->getName());
                    return true;
                }
            }

            /////////////////////////////// DEMOTE ///////////////////////////////

            if ($args[0] == "demote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix sử dụng: /clan demote <Tên người chơi>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải trong clan mới có thể thực hiện được lệnh này."));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi này không online trong clan."));
                    return true;
                }

                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể hạ chức của bản thân được."));
                    return true;
                }
                if (!$this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi này đã là Thành viên."));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();
                $demotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("$args[1] Đã bị hạ chức trở thành Thành viên", true));
                if ($demotee instanceof Player) {
                    $demotee->sendMessage($this->plugin->formatMessage("$prefix Bạn đã bị hạ chức thành Thành viên ở $factionName!", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }

            /////////////////////////////// KICK ///////////////////////////////

            if ($args[0] == "kick") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan kick <Tên người chơi>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan để có thể thực hiện được lệnh này."));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là leader mới có thể sử dụng lệnh này"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Người chơi không online trong clan"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể tự kick bản thân"));
                    return true;
                }
                $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("DELETE FROM master WHERE player = :playername;");
                $stmt->bindvalue(":playername", $args[1]);
                $stmt->execute();

                $sender->sendMessage($this->plugin->formatMessage("Bạn đã kick thành công người chơi $args[1]", true));
                $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                if ($kicked instanceof Player) {
                    $kicked->sendMessage($this->plugin->formatMessage("$prefix Bạn vừa bị kick khỏi Clan \n $factionName", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }


            /////////////////////////////// CLAIM ///////////////////////////////

            if (strtolower($args[0]) == 'claim') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                    $sender->sendMessage($this->plugin->formatMessage("Bạn chỉ có thể chiếm khu đất ở world: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                    return true;
                }

                if ($this->plugin->inOwnPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã chiếm khu vực này rồi."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                    $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                        $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn cần thêm $needed_players Người nữa để có thể bắt đầu thực hiện công việc chiếm khu đất"));
                    return true;
                }
                if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                    $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                    $faction_power = $this->plugin->getFactionPower($faction);
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn không đủ STR để chiếm khu đất."));
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Cần $needed_power STR để có thể có thể chiếm nhưng clan bạn chỉ có $faction_power STR."));
                    return true;
                }

                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ());
                if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

                    return true;
                }

                $sender->sendMessage($this->plugin->formatMessage("Đang tính toán Toạ độ...", true));
                $plot_size = $this->plugin->prefs->get("PlotSize");
                $faction_power = $this->plugin->getFactionPower($faction);
                $sender->sendMessage($this->plugin->formatMessage("Khu đất này đã được chiếm bởi bạn, Xin chúc Mừng.", true));
            }
            if (strtolower($args[0]) == 'plotinfo') {
                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ());
                if (!$this->plugin->isInPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Khu đất này chưa bị ai chiếm, bạn có thể sử dụng /clan clan để bắt đầu chiếm!", true));
                    return true;
                }

                $fac = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                $power = $this->plugin->getFactionPower($fac);
                $sender->sendMessage($this->plugin->formatMessage("$prefix Khu đất này đã bị chiếm bởi $fac với $power STR"));
            }
            if (strtolower($args[0]) == 'top') {
                $this->plugin->sendListOfTop10FactionsTo($sender);
            }
            if (strtolower($args[0]) == 'forcedelete') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan forcedelete <Clan name>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Requested gửi tới clan không tồn tại."));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là OP mới có thể sử dụng lệnh này."));
                    return true;
                }
                $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                $sender->sendMessage($this->plugin->formatMessage("$prefix Đã Delete thành công Clan $args[1]!", true));
            }
            if (strtolower($args[0]) == 'addstrto') {
                if (!isset($args[1]) or !isset($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan addstrto <Clan name> <STR>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Request gửi tới clan không tồn tại."));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là OP mới có thể thực hiện được lệnh này."));
                    return true;
                }
                $this->plugin->addFactionPower($args[1], $args[2]);
                $sender->sendMessage($this->plugin->formatMessage("Thêm thành công $args[2] STR tới Clan $args[1]", true));
            }
            if (strtolower($args[0]) == 'pf') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan pf <Tên người chơi>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Tên người chơi này không tồn tại hoặc không tồn tại trong clan nào."));
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Hãy chắc chắn rằng tên người chơi bạn nhập phải thật chính xác từng chữ."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("-$args[1] Hiện đã ở trong $faction-", true));
            }

            if (strtolower($args[0]) == 'overclaim') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới thực hiện được lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể thực hiện được lệnh này."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($playerName);
                if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                    $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                        $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage($this->plugin->formatMessage("Bạn cần thêm $needed_players Người chơi trong Clan nữa mới có thể thực hiện lệnh này "));
                    return true;
                }
                if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                    $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                    $faction_power = $this->plugin->getFactionPower($faction);
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan bạn không đủ STR để có thể chiếm lấy khu đất này."));
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Cần thêm $needed_power STR để có thể chiếm khu đất như clan bạn chỉ có $faction_power STR."));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Đang tính toán toạ độ...", true));
                $x = floor($sender->getX());
                $z = floor($sender->getZ());
                $level = $sender->getLevel()->getName();
                if ($this->plugin->prefs->get("EnableOverClaim")) {
                    if ($this->plugin->isInPlot($sender)) {
                        $faction_victim = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                        $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                        $faction_ours = $this->plugin->getPlayerFaction($playerName);
                        $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể Overclaim khu đất bạn đã claim."));
                            return true;
                        } else {
                            if ($faction_ours_power < $faction_victim_power) {
                                $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể chiếm lấy khu đất của $faction_victim bởi vì STR của  clan bạn thấp hơn của đối phương."));
                                return true;
                            } else {
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                $this->plugin->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm, $level);
                                $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn đã chiếm lấy đất của $faction_victim Bây giờ nó là của bạn.", true));
                                if ($this->plugin->prefs->get("OverClaimCostsPower")) {
                                    $this->plugin->setFactionPower($faction_ours, $faction_ours_power - $faction_victim_power);
                                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã dùng $faction_victim_power STR để có thể overclaiming $faction_victim", true));
                                }
                                return true;
                            }
                        }
                    } else {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở tromg plot của clan."));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Overclaiming Đã tắt."));
                    return true;
                }
            }


            /////////////////////////////// UNCLAIM ///////////////////////////////

            if (strtolower($args[0]) == "unclaim") {
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới thực hiện được lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader để có thể dùng lệnh này."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                $sender->sendMessage($this->plugin->formatMessage("$prefix Khu đất dủa bạn đã được bãi bỏ.", true));
            }

            /////////////////////////////// DESCRIPTION ///////////////////////////////

            if (strtolower($args[0]) == "desc") {
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan để có thể thực hiện lệnh này"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể thực hiện lệnh này"));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("$prefix Ghi thông điệp của bạn lên khung chat. Nó sẽ không bị ẩn với những người chơi khác", true));
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                $stmt->bindValue(":player", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
            }

            /////////////////////////////// ACCEPT ///////////////////////////////

            if (strtolower($args[0]) == "accept") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không có lời mời nào từ tất cả Clan"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $faction = $array["faction"];
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", ($playerName));
                    $stmt->bindValue(":faction", $faction);
                    $stmt->bindValue(":rank", "Member");
                    $result = $stmt->execute();
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn đã tham gia thành công vào Clan $faction", true));
                    $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $inviter = $this->plugin->getServer()->getPlayerExact($array["invitedby"]);
                    if ($inviter !== null) $inviter->sendMessage($this->plugin->formatMessage("$prefix Người chơi $playerName Vừa tham gia vào Clan của Chúng ta!", true));
                    $this->plugin->updateTag($sender->getName());
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Lời mời đã hết hạn."));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$playerName';");
                }
            }

            /////////////////////////////// DENY ///////////////////////////////

            if (strtolower($args[0]) == "deny") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không có lời mời từ tất cả Clan"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Invite declined", true));
                    $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$prefix Người chơi $playerName Đã từ chối lời mời"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Lời mời đã hết hạn."));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                }
            }

            /////////////////////////////// DELETE ///////////////////////////////

            if (strtolower($args[0]) == "del") {
                if ($this->plugin->isInFaction($playerName) == true) {
                    if ($this->plugin->isLeader($playerName)) {
                        $faction = $this->plugin->getPlayerFaction($playerName);
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã được xoá toàn bộ!", true));
                        $this->plugin->updateTag($sender->getName());
                        unset($this->plugin->factionChatActive[$playerName]);
                        unset($this->plugin->allyChatActive[$playerName]);
                    } else {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn Phải là Leader mới có thể thực hiện lệnh này."));
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không ở trong Clan"));
                }
            }

            /////////////////////////////// LEAVE ///////////////////////////////

            if (strtolower($args[0] == "leave")) {
                if ($this->plugin->isLeader($playerName) == false) {
                    $remove = $sender->getPlayer()->getNameTag();
                    $faction = $this->plugin->getPlayerFaction($playerName);
                    $name = $sender->getName();
                    $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn đã rời thành công clan $faction", true));
                    $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $this->plugin->updateTag($sender->getName());
                    unset($this->plugin->factionChatActive[$playerName]);
                    unset($this->plugin->allyChatActive[$playerName]);
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải xoá clan hoặc Chuyển chức cho ai trước."));
                }
            }

            /////////////////////////////// SETHOME ///////////////////////////////

            if (strtolower($args[0] == "sethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($sender->getName());
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":x", $sender->getX());
                $stmt->bindValue(":y", $sender->getY());
                $stmt->bindValue(":z", $sender->getZ());
                $stmt->bindValue(":world", $sender->getLevel()->getName());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$prefix Đã sethome thành công!", true));
            }

            /////////////////////////////// UNSETHOME ///////////////////////////////

            if (strtolower($args[0] == "unsethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                $sender->sendMessage($this->plugin->formatMessage("$prefix Đã unhome thành công!", true));
            }

            /////////////////////////////// HOME ///////////////////////////////

            if (strtolower($args[0] == "home")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới thực hiện được lệnh này."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (!empty($array)) {
                    if ($array['world'] === null || $array['world'] === "") {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Hoem thất bại, Vui lòng xoá và set lại!"));
                        return true;
                    }
                    if (Server::getInstance()->loadLevel($array['world']) === false) {
                        $sender->sendMessage($this->plugin->formatMessage("The world '" . $array['world'] . "'' Không tìm thấy!"));
                        return true;
                    }
                    $level = Server::getInstance()->getLevelByName($array['world']);
                    $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $level));
                    $sender->sendMessage($this->plugin->formatMessage("Đã về Home clan", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Home clan của bạn chưa set."));
                }
            }

            /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
            if (strtolower($args[0] == "ourmembers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Bạn phải ở trong clan mới có thể thực hiện được lệnh này."));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Member");
            }
            if (strtolower($args[0] == "membersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan membersof <Clan name>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request tới clan này không tìm thấy!"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
            }
            if (strtolower($args[0] == "ourofficers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan để có thể sử dụng lệnh này."));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Officer");
            }
            if (strtolower($args[0] == "officersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan officersof <Clan name>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request tới clan này không tồn tại!"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
            }
            if (strtolower($args[0] == "ourleader")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới có thể sử dụng được lệnh này."));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Leader");
            }
            if (strtolower($args[0] == "leaderof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /clan leaderof <clan name>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request tới clan này không tồn tại."));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
            }
            if (strtolower($args[0] == "c")) {
                if (true) {
                    $sender->sendMessage($this->plugin->formatMessage("/clan chat đã bị tắt"));
                    return true;
                }
                if (!($this->plugin->isInFaction($playerName))) {

                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong Clan mới có thể thực hiện được lệnh chat clan"));
                    return true;
                }
                $r = count($args);
                $row = array();
                $rank = "";
                $f = $this->plugin->getPlayerFaction($playerName);

                if ($this->plugin->isOfficer($playerName)) {
                    $rank = "*";
                } else if ($this->plugin->isLeader($playerName)) {
                    $rank = "**";
                }
                $message = "-> ";
                for ($i = 0; $i < $r - 1; $i = $i + 1) {
                    $message = $message . $args[$i + 1] . " ";
                }
                $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                    $row[$i]['player'] = $resultArr['player'];
                    $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                    if ($p instanceof Player) {
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                    }
                }
            }


            ////////////////////////////// ALLY SYSTEM ////////////////////////////////
            if (strtolower($args[0] == "enemywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan enemywith <clan name>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request tới clan này không tồn tại!"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Không thể trở thành địch với chính clan của bạn."));
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn là đồng minh của clan $args[1]"));
                    return true;
                }
                if ($this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã là kẻ địch của clan $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Leader của kẻ địch hiện offline"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("$prefix Leader của clan $fac đã tuyên chiến clan của bạn là kẻ địch, hãy chống trả!", true));
                }
                $this->plugin->setEnemies($fac, $args[1]);
                $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã trở thành kẻ địch của clan $args[1]!", true));
            }
            if (strtolower($args[0] == "notenemy")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan notenemy <Clan name>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể thực hiện được lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request tới clan này không tồn tại!"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->unsetEnemies($fac, $args[1]);
                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Leader của clan bạn gửi lời đã offline"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("Clan $fac Đã tuyên bố với clan của bạn không còn là kẻ địch", true));
                }
                $sender->sendMessage($this->plugin->formatMessage("$prefix Clan bạn không còn là kẻ địch với clan $args[1]!", true));
            }
            if (strtolower($args[0] == "allywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan allywith <clan name>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan để có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request của clan này không tồn tại"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn đồng minh với chính clan bạn."));
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã là đồng minh với clan $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leaderName = $this->plugin->getLeader($args[1]);
                if (!isset($fac) || !isset($leaderName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan không tồn tại"));
                    return true;
                }
                $leader = $this->plugin->getServer()->getPlayerExact($leaderName);
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);

                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Leader của Clan bạn gửi lời hiện không online"));
                    return true;
                }
                if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã vượt mức giới hạn có thể thêm đồng minh!", false));
                    return true;
                }
                if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã đầy Đồng minh", false));
                    return true;
                }
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                $stmt->bindValue(":player", $leader->getName());
                $stmt->bindValue(":faction", $args[1]);
                $stmt->bindValue(":requestedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn đã gửi lời mời đồng minh tới clan $args[1]!\nVui lòng chờ đợi phản hồi...", true));
                $leader->sendMessage($this->plugin->formatMessage("$prefix Leader của clan $fac Đã gửi lời mời làm đồng minh.\nNhập /clan allyok để chấp nhận hoặc /clan allyno để từ chối.", true));
            }
            if (strtolower($args[0] == "breakalliancewith") or strtolower($args[0] == "notally")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /f breakalliancewith <Clan name>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan để có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là leader để có thể sử dụng lệnh này."));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request của bạn tới clan này không tồn tại"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không thể phá vỡ đồng minh với chính clan của bạn."));
                    return true;
                }
                if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn không còn là đồng minh với clan $args[1]"));
                    return true;
                }

                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->deleteAllies($fac, $args[1]);
                $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("Clan của  $fac Không còn đồng minh với $args[1]", true));
                if ($leader instanceof Player) {
                    $leader->sendMessage($this->plugin->formatMessage("Leader của clan $fac đã phá vỡ đồng minh với clan $args[1]", false));
                }
            }
            if (strtolower($args[0] == "forceunclaim")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Sử dụng: /clan forceunclaim <clan name>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Request của bạn tới clan này không tồn tại."));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là OP mới thực hiện được lệnh này."));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Đã unclaim thành công tới clan $args[1]"));
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
            }

            if (strtolower($args[0] == "  allies")) {
                if (!isset($args[1])) {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể thực hiện được lệnh này."));
                        return true;
                    }

                    $this->plugin->updateAllies($this->plugin->getPlayerFaction($playerName));
                    $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($playerName));
                } else {
                    if (!$this->plugin->factionExists($args[1])) {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Request bạn gửi tới clan này không tồn tại."));
                        return true;
                    }
                    $this->plugin->updateAllies($args[1]);
                    $this->plugin->getAllAllies($sender, $args[1]);
                }
            }
            if (strtolower($args[0] == "allyok")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan để có thể sử dụng lệnh này"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là Leader mới có thể sử dụng lệnh này."));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn không có gửi lời mời đồng minh tới bất kì clan nào."));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->setAllies($requested_fac, $sender_fac);
                    $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $this->plugin->updateAllies($requested_fac);
                    $this->plugin->updateAllies($sender_fac);
                    $this->plugin->unsetEnemies($requested_fac, $sender_fac);
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn đã gửi đơn đồng minh tới $requested_fac", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("Người chơi $playerName từ $sender_fac đã chấp nhận làm đồng minh với clan bạn!", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Đơn quá thời gian, xin thử lại"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }
            if (strtolower($args[0]) == "allyno") {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể sử dụng lệnh này"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải là leader để có thể sử dụng lệnh này."));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan của bạn không gửi bất kì đơn đồng minh nào đến clan khác"));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Clan bạn đã từ chối lời mời đồng minh thành công.", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("Người chơi $playerName từ $sender_fac đã từ chối lời mời đồng minh!"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Đơn đã hết hạn, xin thử lại"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }


            /////////////////////////////// ABOUT ///////////////////////////////

            if (strtolower($args[0] == 'about')) {
                $sender->sendMessage(TextFormat::GREEN . "[ORIGINAL] FactionsPro v1.3.2 by " . TextFormat::BOLD . "Tethered_");
                $sender->sendMessage(TextFormat::GOLD . "[MODDED] This version by MPE and " . TextFormat::BOLD . "Awzaw");
                $sender->sendMessage(TextFormat::RED . "[EDITER] Edit by " . TextFormat::BOLD . "Roxtigger2k3");
            }
            ////////////////////////////// CHAT ////////////////////////////////
            if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("All clan chat đã bị tắt", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->factionChatActive[$playerName])) {
                        unset($this->plugin->factionChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Clan chat bị tắt", false));
                        return true;
                    } else {
                        $this->plugin->factionChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("§aClan chat đã bật", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không có trong clan"));
                    return true;
                }
            }
            if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("All clan chat đã tắt", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->allyChatActive[$playerName])) {
                        unset($this->plugin->allyChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Ally chat đã bật", false));
                        return true;
                    } else {
                        $this->plugin->allyChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("§aAlly chat đã bật", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn không có trong clan"));
                    return true;
                }
            }

            /////////////////////////////// INFO ///////////////////////////////

            if (strtolower($args[0]) == 'info') {
                if (isset($args[1])) {
                    if (!(ctype_alnum($args[1])) or !($this->plugin->factionExists($args[1]))) {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Clan không tồn tại"));
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Hãy thật chắc chắn tên clan bạn nhập là chính xác."));
                        return true;
                    }
                    $faction = $args[1];
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Clan]| : " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Players^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Strength&| : " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Description*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                } else {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("$prefix Bạn phải ở trong clan mới có thể sử dụng lệnh này"));
                        return true;
                    }
                    $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|[Clan]| : " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|^Players^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|&Strength&| : " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "|*Description*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                }
                return true;
            }
            if ($this->plugin->prefs->get("EnableMap") && (strtolower($args[0]) == "map" or strtolower($args[0]) == "m")) {
                $factionPlots = $this->plugin->getNearbyPlots($sender);
                if ($factionPlots == null) {
                    $sender->sendMessage(TextFormat::RED . "$prefix Không có clan nào ở gần");
                    return true;
                }
                $playerFaction = $this->plugin->getPlayerFaction(($sender->getName()));
                $found = false;
                foreach ($factionPlots as $key => $faction) {
                    $plotFaction = $factionPlots[$key]['faction'];
                    if ($plotFaction == $playerFaction) {
                        continue;
                    }
                    if ($this->plugin->isInPlot($sender)) {
                        $inWhichPlot = $this->plugin->factionFromPoint($sender->getX(), $sender->getZ(), $sender->getLevel()->getName());
                        if ($inWhichPlot == $plotFaction) {
                            $sender->sendMessage(TextFormat::GREEN . "Bạn đang ở trong plot của clan " . $plotFaction . " Hãy cẩn thận, họ không thân  thiện đâu!");
                            $found = true;
                            continue;
                        }
                    }
                    $found = true;
                    $x1 = $factionPlots[$key]['x1'];
                    $x2 = $factionPlots[$key]['x2'];
                    $z1 = $factionPlots[$key]['z1'];
                    $z2 = $factionPlots[$key]['z2'];
                    $plotX = $x1 + ($x2 - $x1) / 2;
                    $plotZ = $z1 + ($z2 - $z1) / 2;
                    $deltaX = $plotX - $sender->getX();
                    $deltaZ = $plotZ - $sender->getZ();
                    $bearing = rad2deg(atan2($deltaZ, $deltaX));
                    if ($bearing >= -22.5 && $bearing < 22.5) $direction = "south";
                    else if ($bearing >= 22.5 && $bearing < 67.5) $direction = "southwest";
                    else if ($bearing >= 67.5 && $bearing < 112.5) $direction = "west";
                    else if ($bearing >= 112.5 && $bearing < 157.5) $direction = "northwest";
                    else if ($bearing >= 157.5) $direction = "north";
                    else if ($bearing < -22.5 && $bearing > -67.5) $direction = "southeast";
                    else if ($bearing <= -67.5 && $bearing > -112.5) $direction = "east";
                    else if ($bearing <= -112.5 && $bearing > -157.5) $direction = "northeast";
                    else if ($bearing <= -157.5) $direction = "north";
                    $distance = floor(sqrt(pow($deltaX, 2) + pow($deltaZ, 2)));
                    $sender->sendMessage(TextFormat::GREEN . $plotFaction . "'s plot is " . $distance . " blocks " . $direction);
                }
                if (!$found) {
                    $sender->sendMessage(TextFormat::RED . "Không có clan nào ở gần");
                } else {
                    $points = ["south", "west", "north", "east"];
                    $sender->sendMessage(TextFormat::YELLOW . "You are facing " . $points[$sender->getDirection()]);
                }
            }
            if (strtolower($args[0]) == "help") {
                $sender->sendMessage(TextFormat::RED . "\n/clan about\n/clan accept\n/clan overclaim [Takeover the plot of the requested clan]\n/clan claim\n/clan create <name>\n/clan del\n/clan demote <Tên người chơi>\n/clan deny");
                $sender->sendMessage(TextFormat::RED . "\n/can home\n/clan help <#page>\n/clan info\n/clan info <clan name>\n/clan invite <tên người chơi>\n/clan kick <Tên người chơi>\n/clan leader <Tên người chơi>\n/clan leave");
                $sender->sendMessage(TextFormat::RED . "\n/clan sethome\n/clan unclaim\n/clan unsethome\n/clan ourmembers - {Members + Statuses}\n/clan ourofficers - {Officers + Statuses}\n/clan ourleader - {Leader + Status}\n/clan allies - {Danh sách đồng minh của bạn");
                $sender->sendMessage(TextFormat::RED . "\n/clan desc\n/clan promote <Tên người chơi>\n/clan allywith <Clan name>\n/clan breakalliancewith <clan name>\n\n/clan allyok [Accept a request for alliance]\n/clan allyno [Deny a request for alliance]\n/clan allies <clan name> - {The allies of your chosen clan}");
                $sender->sendMessage(TextFormat::RED . "\n/clan membersof <clan name>\n/clan officersof <clan name>\n/f leaderof <Clan name>\n/ clan say <send message to everyone in your clan>\n/clan pf <Tên người chơi>\n/clan top");
                $sender->sendMessage(TextFormat::RED . "\n/clan forceunclaim <clan name> [Unclaim a clan plot by force - OP]\n\n/clan forcedelete <Clan name> [Delete a clan by force - OP]");
                return true;
            }
            return true;
          }
          return true;
       }
       public function alphanum($string) {
        if (function_exists('ctype_alnum')) {
            $return = ctype_alnum($string);
        } else {
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }
}