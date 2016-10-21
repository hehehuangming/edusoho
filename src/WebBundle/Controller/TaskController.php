<?php
namespace WebBundle\Controller;

use Biz\Task\Service\TaskService;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Service\Common\ServiceKernel;
use Topxia\Service\Course\Impl\CourseServiceImpl;

class TaskController extends BaseController
{
    public function showAction(Request $request, $courseId, $id)
    {
        $task = $this->tryLearnTask($courseId, $id);

        return $this->render('TaskBundle:Task:show.html.twig', array(
            'task' => $task
        ));
    }

    public function taskActivityAction(Request $request, $courseId, $id)
    {
        $task = $this->tryLearnTask($courseId, $id);

        return $this->forward('ActivityBundle:Activity:show', array(
            'task' => $task
        ));
    }

    public function triggerAction(Request $request, $courseId, $id, $eventName)
    {
        $task         = $this->tryLearnTask($courseId, $id);
        $data         = $request->request->all();
        $data['task'] = $task;

        return $this->forward('ActivityBundle:Activity:trigger', array(
            'id'        => $task['activityId'],
            'eventName' => $eventName,
            'data'      => $data
        ));
    }

    public function finishAction(Request $request, $courseId, $id)
    {

    }

    protected function tryLearnTask($courseId, $taskId)
    {
        $this->getCourseService()->tryLearnCourse($courseId);
        $task = $this->getTaskService()->getTask($taskId);
        if ($task['courseId'] != $courseId) {
            throw $this->createAccessDeniedException();
        }
        return $task;
    }

    /**
     * @return CourseServiceImpl
     */
    protected function getCourseService()
    {
        return ServiceKernel::instance()->createService('Course.CourseService');
    }

    /**
     * @return TaskService
     */
    protected function getTaskService()
    {
        return $this->createService('CourseTask:Task.TaskService');
    }
}