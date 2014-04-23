<?php
/**
 * Created by PhpStorm.
 * User: Leonardo Shinagawa
 * Date: 12/02/14
 * Time: 10:45
 */

namespace shina\controlmybudget\dataprovider;


use Doctrine\DBAL\Connection;
use ebussola\goalr\Event;
use shina\controlmybudget\DataProvider;
use shina\controlmybudget\MonthlyGoal;

class DoctrineDBAL implements DataProvider {

    protected $_purchase_table_name = 'purchase';
    protected $_purchase_fields = array('p.id', 'p.date', 'p.place', 'p.amount');

    protected $_monthly_goal_table_name = 'monthly_goal';
    protected $_monthly_goal_fields = array('mg.id', 'mg.month', 'mg.year', 'mg.amount_goal');

    protected $_event_table_name = 'event';
    protected $_event_fields = array('e.id', 'e.monthly_goal_id', 'e.name', 'e.date_start', 'e.date_end',
        'e.variation', 'e.category');

    /**
     * @var Connection
     */
    private $conn;

    public function __construct(Connection $conn) {
        $this->conn = $conn;
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public function insertPurchase(array $data) {
        $this->conn->insert('purchase', $data);

        return $this->conn->lastInsertId();
    }

    /**
     * @param int   $id
     * @param array $data
     *
     * @return bool
     */
    public function updatePurchase($id, array $data) {
        return $this->conn->update('purchase', $data, array('id' => $id)) === 1;
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public function savePurchase(array $data) {
        if (isset($data['id']) && $data['id'] != null) {
            $this->updatePurchase($data['id'], $data);

            return $data['id'];
        } else {
            $id = $this->insertPurchase($data);

            return $id;
        }
    }

    /**
     * @param \DateTime $date_start
     * @param \DateTime $date_end
     *
     * @return array
     */
    public function findPurchasesByPeriod(\DateTime $date_start, \DateTime $date_end) {
        $query = $this->conn->createQueryBuilder()
            ->select($this->_purchase_fields)
            ->from($this->_purchase_table_name, 'p')
            ->where('date >= ?')
            ->andWhere('date <= ?');

        $data = $this->conn->executeQuery($query, array(
            $date_start->format('Y-m-d'),
            $date_end->format('Y-m-d')
        ))->fetchAll();

        return $data;
    }

    /**
     * @param string $hash
     *
     * @return array
     */
    public function findPurchaseByHash($hash)
    {
        $query = $this->conn->createQueryBuilder()
            ->select($this->_purchase_fields)
            ->from($this->_purchase_table_name, 'p')
            ->where('hash = ?');

        $data = $this->conn->executeQuery($query, [$hash])->fetch();

        return $data;
    }

    /**
     * @param array $data
     *
     * @return int
     * ID of the added object
     */
    public function insertMonthlyGoal(array $data) {
        $events = $data['events'];
        unset($data['events']);

        $this->conn->insert('monthly_goal', $data);
        $monthly_goal_id = $this->conn->lastInsertId();

        $this->saveEvents($events, $monthly_goal_id);

        return $monthly_goal_id;
    }

    /**
     * @param int   $id
     * @param array $data
     *
     * @return bool
     */
    public function updateMonthlyGoal($id, array $data) {
        $events = $data['events'];
        unset($data['events']);

        $this->conn->update('monthly_goal', $data, array('id' => $data['id']));

        $this->saveEvents($events, $data['id']);
    }

    /**
     * @param int $month
     * @param int $year
     *
     * @return MonthlyGoal[]
     */
    public function findMonthlyGoalsByMonthAndYear($month, $year) {
        $query = $this->conn->createQueryBuilder()
            ->select($this->_monthly_goal_fields)
            ->from($this->_monthly_goal_table_name, 'mg')
            ->where('mg.month = ?')
            ->andWhere('mg.year = ?');
        $monthly_goals_data = $this->conn->executeQuery($query, array(
            $month, $year
        ))->fetchAll();
        $monthly_goals_data = $this->fillWithEvents($monthly_goals_data);

        return $monthly_goals_data;
    }

    /**
     * @param \DateTime $date_start
     * @param \DateTime $date_end
     *
     * @return float
     */
    public function calcAmountByPeriod(\DateTime $date_start, \DateTime $date_end) {
        $query = $this->conn->createQueryBuilder()
            ->select(array('SUM(p.amount) as count_amount'))
            ->from($this->_purchase_table_name, 'p')
            ->where('p.date >= ?')
            ->andWhere('p.date <= ?');
        $data = $this->conn->executeQuery($query, array(
            $date_start->format('Y-m-d'),
            $date_end->format('Y-m-d')
        ))->fetchAll();

        return (float) reset($data)['count_amount'];
    }

    /**
     * @param int[] $monthly_goal_ids
     *
     * @return MonthlyGoal[]
     */
    public function findMonthlyGoalByIds($monthly_goal_ids) {
        $query = $this->conn->createQueryBuilder()
            ->select($this->_monthly_goal_fields)
            ->from($this->_monthly_goal_table_name, 'mg')
            ->where('mg.id IN (?)');
        $monthly_goals_data = $this->conn->executeQuery($query, array(
            $monthly_goal_ids
        ), array(
            Connection::PARAM_INT_ARRAY
        ))->fetchAll();
        $monthly_goals_data = $this->fillWithEvents($monthly_goals_data);

        return $monthly_goals_data;
    }

    /**
     * @param Event[] $events
     * @param int     $monthly_goal_id
     */
    private function saveEvents($events, $monthly_goal_id) {
        foreach ($events as $event_data) {
            $event_data['monthly_goal_id'] = $monthly_goal_id;
            if ($event_data['id'] == null) {
                $this->conn->insert('event', $event_data);
            } else {
                $this->conn->update('event', $event_data, array('id' => $event_data['id']));
            }
        }
    }

    /**
     * @param $monthly_goals_data
     *
     * @return mixed
     */
    private function fillWithEvents($monthly_goals_data) {
        // reducing ids of monthly_goals to monthly_goal_ids
        $monthly_goal_ids = array();
        foreach ($monthly_goals_data as $monthly_goal_data) {
            $monthly_goal_ids[] = $monthly_goal_data['id'];
        }

        $query = $this->conn->createQueryBuilder()
            ->select($this->_event_fields)
            ->from($this->_event_table_name, 'e')
            ->where('monthly_goal_id IN (?)');
        $events_data = $this->conn->executeQuery($query, array(
            $monthly_goal_ids
        ), array(
            Connection::PARAM_INT_ARRAY
        ))->fetchAll();

        // coupling events on monthly_goals
        foreach ($monthly_goals_data as &$monthly_goal_data) {
            $monthly_goal_data['events'] = array();

            foreach ($events_data as $event_data) {
                if ($event_data['monthly_goal_id'] == $monthly_goal_data['id']) {
                    $monthly_goal_data['events'][] = $event_data;
                }
            }
        }

        return $monthly_goals_data;
    }

}