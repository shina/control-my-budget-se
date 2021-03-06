<?php
/**
 * Created by PhpStorm.
 * User: Leonardo Shinagawa
 * Date: 12/02/14
 * Time: 16:44
 */

namespace shina\controlmybudget\controller;

use ebussola\goalr\event\Event;
use shina\controlmybudget\MonthlyGoal\MonthlyGoal;

class MonthlyGoalController extends AbstractOAuthController {

    public function goalAction($month=null, $year=null) {
        if ($month === null) {
            $month = date('m');
        }
        if ($year === null) {
            $year = date('Y');
        }

        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->container->get('monthly_goal_service');
        $monthly_goals = $monthly_goal_service->getMonthlyGoalByMonthAndYear($month, $year, $this->user);

        $this->app->response->setBody($this->monthlyGoalsToJson($monthly_goals));
    }

    public function allGoalsAction()
    {
        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->monthly_goal_service;
        $monthly_goals = $monthly_goal_service->getAll($this->user);

        $this->app->response->setBody($this->monthlyGoalsToJson($monthly_goals));
    }

    public function addGoalAction() {
        $data = json_decode($this->app->request->post('monthly_goal'), true);
        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->container->get('monthly_goal_service');

        $monthly_goal = $this->fillMonthlyGoal(new MonthlyGoal(), $data);
        $monthly_goal_service->save($monthly_goal, $this->user);

        $this->app->response->setBody(json_encode($this->monthlyGoalToArray($monthly_goal)));
    }

    public function editGoalAction($monthly_goal_id) {
        $data = json_decode($this->app->request->post('monthly_goal'), true);
        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->container->get('monthly_goal_service');

        $monthly_goal = $monthly_goal_service->getMonthlyGoalById($monthly_goal_id);
        $monthly_goal = $this->fillMonthlyGoal($monthly_goal, $data);

        $monthly_goal_service->save($monthly_goal, $this->user);
    }

    public function getGoalAction($monthly_goal_id) {
        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->container->get('monthly_goal_service');

        $monthly_goal = $monthly_goal_service->getMonthlyGoalById($monthly_goal_id);

        $this->app->response->setBody(json_encode($this->monthlyGoalToArray($monthly_goal)));
    }

    /**
     * @param $monthly_goal_id
     */
    public function deleteGoalAction($monthly_goal_id)
    {
        /** @var \shina\controlmybudget\MonthlyGoalService $monthly_goal_service */
        $monthly_goal_service = $this->app->container->get('monthly_goal_service');

        if (!$monthly_goal_service->delete($monthly_goal_id)) {
            $this->app->response->setStatus(400);
        }
    }

    /**
     * @param $monthly_goals
     *
     * @return string
     */
    private function monthlyGoalsToJson($monthly_goals) {
        $monthly_goals = $this->monthlyGoalsToArray($monthly_goals);

        return json_encode($monthly_goals);
    }

    /**
     * @param $data
     *
     * @return MonthlyGoal
     */
    private function fillMonthlyGoal(MonthlyGoal $monthly_goal, $data) {
        if (isset($data['month'])) {
            $monthly_goal->month = $data['month'];
        }

        if (isset($data['year'])) {
            $monthly_goal->year = $data['year'];
        }

        if (isset($data['amount_goal'])) {
            $monthly_goal->amount_goal = $data['amount_goal'];
        }

        if (isset($data['events'])) {
            $monthly_goal->events = array();

            foreach ($data['events'] as $event_data) {
                $event = new Event();
                if (isset($event_data['id'])) {
                    $event->id = $event_data['id'];
                }
                $event->name = $event_data['name'];
                $event->date_start = new \DateTime($event_data['date_start']);
                $event->date_end = new \DateTime($event_data['date_end']);
                $event->variation = $event_data['variation'];
                $event->category = $event_data['category'];

                $monthly_goal->events[] = $event;
            }
        }

        return $monthly_goal;
    }

    /**
     * @param $monthly_goals
     *
     * @return array
     */
    private function monthlyGoalsToArray($monthly_goals) {
        foreach ($monthly_goals as &$monthly_goal) {
            $monthly_goal = $this->monthlyGoalToArray($monthly_goal);
        }

        return $monthly_goals;
    }

    /**
     * @param $monthly_goal
     * @param $event
     *
     * @return array
     */
    private function monthlyGoalToArray($monthly_goal) {
        foreach ($monthly_goal->events as &$event) {
            $event->date_start = $event->date_start->format('Y-m-d');
            $event->date_end = $event->date_end->format('Y-m-d');
            $event = (array)$event;
        }

        $monthly_goal = (array)$monthly_goal;

        return $monthly_goal;
    }

}