<?php
/**
 * @api {OBJECT} Ticket Ticket
 * @apiVersion 4.3.0
 * @apiGroup Data Structures
 * @apiParam {Number}  ticketNumber The number of the ticket.
 * @apiParam {String}  title The title of the ticket.
 * @apiParam {String}  content The content of the ticket.
 * @apiParam {Object}  department The department of the ticket.
 * @apiParam {Number}  department.id The id of the department of the ticket.
 * @apiParam {String}  department.name The department's name of the ticket.
 * @apiParam {String}  file The filename of the ticket if attached.
 * @apiParam {String}  language The language of the ticket.
 * @apiParam {Boolean}  unread Indicates if the user has already read the last comment.
 * @apiParam {Boolean}  unreadStaff Indicates if the staff has already read the last comment.
 * @apiParam {Boolean}  closed Indicates if the ticket is closed.
 * @apiParam {String}  priority The priority of the ticket. It can be LOW, MEDIUM or HIGH.
 * @apiParam {Object}  author The author of the ticket.
 * @apiParam {Number}  author.id The id of the author of the ticket.
 * @apiParam {String}  author.name The author's name of the ticket.
 * @apiParam {String}  author.email The author's email of the ticket.
 * @apiParam {Object}  owner The owner of the ticket.
 * @apiParam {Number}  owner.id The owner's id of the ticket.
 * @apiParam {String}  owner.name The owner's name of the ticket.
 * @apiParam {String}  owner.email The owner's email of the ticket.
 * @apiParam {[TicketEvent](#api-Data_Structures-ObjectTicketevent)[]} events Events related to the ticket.
 */
use RedBeanPHP\Facade as RedBean;

class Ticket extends DataStore {
    const TABLE = 'ticket';

    public static function getProps() {
        return array(
            'ticketNumber',
            'title',
            'content',
            'language',
            'department',
            'file',
            'date',
            'unread',
            'closed',
            'priority',
            'author',
            'authorStaff',
            'owner',
            'ownTicketeventList',
            'unreadStaff',
            'language',
            'authorEmail',
            'authorName'
        );
    }

    public static function getTicket($value, $property = 'id') {
        return parent::getDataStore($value, $property);
    }

    public static function getByTicketNumber($value) {
        return Ticket::getTicket($value, 'ticketNumber');
    }

    public function setAuthor($author) {
        if($author instanceof User) {
            $this->author = $author;
        } else if($author instanceof Staff) {
            $this->authorStaff = $author;
        }
    }

    public function getAuthor() {
        if($this->author && !$this->author->isNull()) {
            return $this->author;
        } else {
            return $this->authorStaff;
        }
    }

    public function getDefaultProps() {
        return array(
            'priority' => 'low',
            'unread' => false,
            'unreadStaff' => true,
            'ticketNumber' => $this->generateUniqueTicketNumber()
        );
    }

    public function store() {
        parent::store();
    }

    public function generateUniqueTicketNumber() {
        $linearCongruentialGenerator = new LinearCongruentialGenerator();
        $ticketQuantity = Ticket::count();

        if ($ticketQuantity === 0) {
            $ticketNumber = Setting::getSetting('ticket-first-number')->value;
        } else {
            $linearCongruentialGenerator->setGap(Setting::getSetting('ticket-gap')->value);
            $linearCongruentialGenerator->setFirst(Setting::getSetting('ticket-first-number')->value);

            $ticketNumber = $linearCongruentialGenerator->generate($ticketQuantity);
        }

        return $ticketNumber;
    }

    public function toArray() {
        return [
            'ticketNumber' => $this->ticketNumber,
            'title' => $this->title,
            'content' => $this->content,
            'department' => [
                'id' => $this->department->id,
                'name' => $this->department->name
            ],
            'date' => $this->date,
            'file' => $this->file,
            'language' => $this->language,
            'unread' => !!$this->unread,
            'unreadStaff' => !!$this->unreadStaff,
            'closed' => !!$this->closed,
            'priority' => $this->priority,
            'author' => $this->authorToArray(),
            'owner' => $this->ownerToArray(),
            'events' => $this->eventsToArray()
        ];
    }

    public function authorToArray() {
        $author = $this->getAuthor();

        if ($author && !$author->isNull()) {
            return [
                'id' => $author->id,
                'name' => $author->name,
                'staff' => $author instanceof Staff,
                'profilePic' => ($author instanceof Staff) ? $author->profilePic : null,
                'email' => $author->email
            ];
        } else {
            return [
              'id' => NULL,
              'staff' => false,
              'name' => $this->authorName,
              'email' => $this->authorEmail
            ];
        }
    }

    public function ownerToArray() {
        $owner = $this->owner;

        if ($owner && !$owner->isNull()) {
            return [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email
            ];
        } else {
            return null;
        }
    }

    public function eventsToArray() {
        $events = [];

        foreach ($this->ownTicketeventList as $ticketEvent) {
            $event = [
                'type' => $ticketEvent->type,
                'content'=> $ticketEvent->content,
                'author' => [],
                'date'=> $ticketEvent->date,
                'file'=> $ticketEvent->file,
                'private'=> $ticketEvent->private,
            ];

            $author = $ticketEvent->getAuthor();
            if($author && !$author->isNull()) {
                $event['author'] = [
                    'id'=> $author->id,
                    'name' => $author->name,
                    'email' =>$author->email,
                    'profilePic' => ($author instanceof Staff) ? $author->profilePic : null,
                    'staff' => $author instanceof Staff
                ];
            }

            if(!Controller::isStaffLogged() && $ticketEvent->private) {
                continue;
            }

            $events[] = $event;
        }

        return $events;
    }

    public function addEvent(Ticketevent $event) {
        $this->ownTicketeventList->add($event);
    }

    public function isAuthor($user) {
        $ticketAuthor = $this->authorToArray();
        if(is_string($user)) return $user == $ticketAuthor['email'];
        if(!($user instanceof DataStore) || $user->isNull()) return false;
        return $user->id == $ticketAuthor['id'] && ($user instanceof Staff) == $ticketAuthor['staff'];
    }

    public function isOwner($user) {
        return !$user->isNull() && $this->owner && $user->id == $this->owner->id && ($user instanceof Staff);
    }
}
