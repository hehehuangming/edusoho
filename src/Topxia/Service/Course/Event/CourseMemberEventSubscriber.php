<?php
namespace Topxia\Service\Course\Event;

use Topxia\Common\ArrayToolkit;
use Topxia\Service\Common\ServiceEvent;
use Topxia\Service\Common\ServiceKernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CourseMemberEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'course.update'        => 'onCourseUpdate',
            'course.lesson.create' => 'onCourseLessonCreate',
            'course.lesson.delete' => 'onCourseLessonDelete',
            'course.lesson_finish' => 'onLessonFinish'
        );
    }

    public function onCourseUpdate(ServiceEvent $event)
    {
        $context      = $event->getSubject();
        $sourceCourse = $context['sourceCourse'];
        $course       = $context['course'];

        if ($sourceCourse['serializeMode'] != $course['serializeMode']) {
            if ($course['serializeMode'] == 'serialize') {
                $courseMembers = $this->getCourseService()->searchMembers(
                    array(
                        'courseId'  => $course['id'],
                        'isLearned' => 1
                    ),
                    array('createdTime', 'DESC'),
                    0, PHP_INT_MAX
                );
                $updateFields = array('isLearned' => 0);
                $this->updateCourseMembers($courseMembers, $updateFields);
            } elseif ($sourceCourse['serializeMode'] == 'serialize' && $course['serializeMode'] != 'serialize') {
                $courseMembers = $this->getCourseService()->searchMembers(
                    array(
                        'courseId'              => $course['id'],
                        'learnedNumGreaterThan' => $course['lessonNum']
                    ),
                    array('createdTime', 'DESC'),
                    0, PHP_INT_MAX
                );

                $updateFields = array('isLearned' => 1);
                $this->updateCourseMembers($courseMembers, $updateFields);
            }
        }
    }

    public function onCourseLessonCreate(ServiceEvent $event)
    {
        $context  = $event->getSubject();
        $argument = $context['argument'];
        $lesson   = $context['lesson'];

        $course = $this->getCourseService()->getCourse($lesson['courseId']);

        $courseMembers = $this->getCourseService()->searchMembers(
            array(
                'courseId'  => $course['id'],
                'isLearned' => 1
            ),
            array('createdTime', 'DESC'),
            0, PHP_INT_MAX
        );

        if ($courseMembers && $course['serializeMode'] != 'serialize') {
            foreach ($courseMembers as $key => $member) {
                if ($member['learnedNum'] < $course['lessonNum']) {
                    $memberFields = array(
                        'isLearned' => 0
                    );

                    $this->getCourseService()->updateCourseMember($member['id'], $memberFields);
                }
            }
        }
    }

    public function onCourseLessonDelete(ServiceEvent $event)
    {
        $context  = $event->getSubject();
        $lesson   = $context['lesson'];
        $courseId = $context['courseId'];

        $course = $this->getCourseService()->getCourse($lesson['courseId']);

        $courseMembers = $this->getCourseService()->searchMembers(
            array(
                'courseId'              => $course['id'],
                'learnedNumGreaterThan' => $course['lessonNum']
            ),
            array('createdTime', 'DESC'),
            0, PHP_INT_MAX
        );

        if ($courseMembers && $course['serializeMode'] != 'serialize') {
            $updateFields = array(
                'isLearned'  => 1,
                'learnedNum' => $course['lessonNum']
            );
            $this->updateCourseMembers($courseMembers, $updateFields);
        }
    }

    public function onLessonFinish(ServiceEvent $event)
    {
        $lesson = $event->getSubject();
        $course = $event->getArgument('course');
        $learn  = $event->getArgument('learn');

        if ($course['status'] != 'published') {
            return false;
        }

        $conditions = array(
            'userId'   => $learn['userId'],
            'courseId' => $learn['courseId'],
            'status'   => 'finished'
        );
        $userLearnCount = $this->getCourseService()->searchLearnCount($conditions);
        $userLearns     = $this->getCourseService()->searchLearns(
            $conditions,
            array('finishedTime', 'DESC'),
            0, $userLearnCount
        );

        $totalCredits = $this->getCourseService()->sumLessonGiveCreditByLessonIds(ArrayToolkit::column($userLearns, 'lessonId'));

        $memberFields               = array();
        $memberFields['learnedNum'] = $userLearnCount;

        if ($course['serializeMode'] != 'serialize') {
            $memberFields['isLearned'] = $memberFields['learnedNum'] >= $course['lessonNum'] ? 1 : 0;
        }

        $memberFields['credit'] = $totalCredits;

        $courseMember = $this->getCourseService()->getCourseMember($course['id'], $learn['userId']);
        $this->getCourseService()->updateCourseMember($courseMember['id'], $memberFields);
    }

    protected function updateCourseMembers($members, $updateFields)
    {
        if (!$members) {
            return false;
        }

        foreach ($members as $key => $member) {
            $this->getCourseService()->updateCourseMember($member['id'], $updateFields);
        }

        return true;
    }

    protected function getCourseService()
    {
        return ServiceKernel::instance()->createService('Course.CourseService');
    }
}
