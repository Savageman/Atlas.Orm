<?php
namespace Atlas\Orm;

use Atlas\Orm\DataSource\SchoolAtlasContainer;
use Atlas\Orm\DataSource\Degree\DegreeMapper;
use Atlas\Orm\DataSource\Student\StudentMapper;
use Aura\Sql\ExtendedPdo;

class AtlasCompositeTest extends \PHPUnit\Framework\TestCase
{
    protected $atlas;

    // The $expect* properties are at the end, because they are so long

    protected function setUp()
    {
        $connection = new ExtendedPdo('sqlite::memory:');
        $fixture = new SqliteFixture($connection);
        $fixture->exec();

        $atlasContainer = new SchoolAtlasContainer($connection);
        $this->atlas = $atlasContainer->getAtlas();
    }

    public function testFetchRecord()
    {
        $actual = $this->atlas->fetchRecord(
            StudentMapper::CLASS,
            ['student_fn' => 'Anna', 'student_ln' => 'Alpha'],
            [
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ]
        )->getArrayCopy();

        $this->assertSame($this->expectRecord, $actual);
    }

    public function testFetchRecordBy()
    {
        $actual = $this->atlas->fetchRecordBy(
            StudentMapper::CLASS,
            ['student_fn' => 'Anna'],
            [
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ]
        )->getArrayCopy();

        $this->assertSame($this->expectRecord, $actual);
    }

    public function testFetchRecordSet()
    {
        $actual = $this->atlas->fetchRecordSet(
            StudentMapper::CLASS,
            [
                ['student_fn' => 'Anna', 'student_ln' => 'Alpha'],
                ['student_fn' => 'Betty', 'student_ln' => 'Beta'],
                ['student_fn' => 'Clara', 'student_ln' => 'Clark'],
            ],
            [
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ]
        )->getArrayCopy();

        foreach ($this->expectRecordSet as $i => $expect) {
            $this->assertSame($expect, $actual[$i], "record $i not the same");
        }
    }

    public function testFetchRecordSetBy()
    {
        // note that we canno to
        $actual = $this->atlas->fetchRecordSetBy(
            StudentMapper::CLASS,
            ['student_fn' => ['Anna', 'Betty', 'Clara']],
            [
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ]
        )->getArrayCopy();

        foreach ($this->expectRecordSet as $i => $expect) {
            $this->assertSame($expect, $actual[$i], "record $i not the same");
        }
    }

    public function testSelect_fetchRecord()
    {
        $actual = $this->atlas
            ->select(StudentMapper::CLASS)
            ->where('student_fn = ?', 'Anna')
            ->with([
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ])
            ->fetchRecord();

        $this->assertSame($this->expectRecord, $actual->getArrayCopy());
    }

    public function testSelect_fetchRecordSet()
    {
        $actual = $this->atlas
            ->select(StudentMapper::CLASS)
            ->where('student_fn < ?', 'D')
            ->with([
                'degree',
                'gpa',
                'enrollments',
                'courses',
            ])
            ->fetchRecordSet()
            ->getArrayCopy();

        foreach ($this->expectRecordSet as $i => $expect) {
            $this->assertSame($expect, $actual[$i], "record $i not the same");
        }
    }

    public function testSingleRelatedInRecordSet()
    {
        $degree = $this->atlas->fetchRecordBy(
            DegreeMapper::CLASS,
            [
                'degree_type' => 'BS',
                'degree_subject' => 'MATH',
            ]
        );
        $expect = $degree->getRow();

        $students = $this->atlas->fetchRecordSetBy(
            StudentMapper::CLASS,
            [
                'degree_type' => 'BS',
                'degree_subject' => 'MATH',
            ],
            [
                'degree',
            ]
        );

        foreach ($students as $student) {
            $actual = $student->degree->getRow();
            $this->assertSame($expect, $actual);
        }
    }

    public function testCalcPrimaryComposite_missingKey()
    {
        $this->expectException(
            'Atlas\Orm\Exception',
            "Expected scalar value for primary key 'student_ln', value is missing instead."
        );
        $this->atlas->fetchRecord(StudentMapper::CLASS, ['student_fn' => 'Anna']);
    }

    public function testCalcPrimaryComposite_nonScalar()
    {
        $this->expectException(
            'Atlas\Orm\Exception',
            "Expected scalar value for primary key 'student_fn', got array instead."
        );
        $this->atlas->fetchRecord(
            StudentMapper::CLASS,
            ['student_fn' => ['Anna', 'Betty', 'Clara']]
        );
    }

    public function testCalcPrimaryComposite()
    {
        $actual = $this->atlas->fetchRecord(
            StudentMapper::CLASS,
            [
                'foo' => 'bar',
                'student_fn' => 'Anna',
                'student_ln' => 'Alpha',
                'baz' => 'dib',
            ]
        );

        $this->assertSame('Anna', $actual->student_fn);
        $this->assertSame('Alpha', $actual->student_ln);
    }

    protected $expectRecord = [
        'student_fn' => 'Anna',
        'student_ln' => 'Alpha',
        'degree_type' => 'BA',
        'degree_subject' => 'ENGL',
        'gpa' => [
            'student_fn' => 'Anna',
            'student_ln' => 'Alpha',
            'gpa' => '1.333',
            'student' => null,
        ],
        'degree' => [
            'degree_type' => 'BA',
            'degree_subject' => 'ENGL',
            'title' => 'Bachelor of Arts, English',
            'students' => null,
        ],
        'enrollments' => [
            0 => [
                'student_fn' => 'Anna',
                'student_ln' => 'Alpha',
                'course_subject' => 'ENGL',
                'course_number' => '100',
                'grade' => '65',
                'points' => '1',
                'course' => null,
                'student' => null,
            ],
            1 => [
                'student_fn' => 'Anna',
                'student_ln' => 'Alpha',
                'course_subject' => 'HIST',
                'course_number' => '100',
                'grade' => '68',
                'points' => '1',
                'course' => null,
                'student' => null,
            ],
            2 => [
                'student_fn' => 'Anna',
                'student_ln' => 'Alpha',
                'course_subject' => 'MATH',
                'course_number' => '100',
                'grade' => '71',
                'points' => '2',
                'course' => null,
                'student' => null,
            ],
        ],
        'courses' => [
            0 => [
                'course_subject' => 'ENGL',
                'course_number' => '100',
                'title' => 'Composition',
                'enrollments' => null,
                'students' => null,
            ],
            1 => [
                'course_subject' => 'HIST',
                'course_number' => '100',
                'title' => 'World History',
                'enrollments' => null,
                'students' => null,
            ],
            2 => [
                'course_subject' => 'MATH',
                'course_number' => '100',
                'title' => 'Algebra',
                'enrollments' => null,
                'students' => null,
            ],
        ],
    ];

    protected $expectRecordSet = [
        0 => [
            'student_fn' => 'Anna',
            'student_ln' => 'Alpha',
            'degree_type' => 'BA',
            'degree_subject' => 'ENGL',
            'gpa' => [
                'student_fn' => 'Anna',
                'student_ln' => 'Alpha',
                'gpa' => '1.333',
                'student' => NULL,
            ],
            'degree' => [
                'degree_type' => 'BA',
                'degree_subject' => 'ENGL',
                'title' => 'Bachelor of Arts, English',
                'students' => NULL,
            ],
            'enrollments' => [
                0 => [
                    'student_fn' => 'Anna',
                    'student_ln' => 'Alpha',
                    'course_subject' => 'ENGL',
                    'course_number' => '100',
                    'grade' => '65',
                    'points' => '1',
                    'course' => NULL,
                    'student' => NULL,
                ],
                1 => [
                    'student_fn' => 'Anna',
                    'student_ln' => 'Alpha',
                    'course_subject' => 'HIST',
                    'course_number' => '100',
                    'grade' => '68',
                    'points' => '1',
                    'course' => NULL,
                    'student' => NULL,
                ],
                2 => [
                    'student_fn' => 'Anna',
                    'student_ln' => 'Alpha',
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'grade' => '71',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
            ],
            'courses' => [
                0 => [
                    'course_subject' => 'ENGL',
                    'course_number' => '100',
                    'title' => 'Composition',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                1 => [
                    'course_subject' => 'HIST',
                    'course_number' => '100',
                    'title' => 'World History',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                2 => [
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'title' => 'Algebra',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
            ],
        ],
        1 => [
            'student_fn' => 'Betty',
            'student_ln' => 'Beta',
            'degree_type' => 'MA',
            'degree_subject' => 'HIST',
            'gpa' => [
                'student_fn' => 'Betty',
                'student_ln' => 'Beta',
                'gpa' => '1.667',
                'student' => NULL,
            ],
            'degree' => [
                'degree_type' => 'MA',
                'degree_subject' => 'HIST',
                'title' => 'Master of Arts, History',
                'students' => NULL,
            ],
            'enrollments' => [
                0 => [
                    'student_fn' => 'Betty',
                    'student_ln' => 'Beta',
                    'course_subject' => 'ENGL',
                    'course_number' => '200',
                    'grade' => '74',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
                1 => [
                    'student_fn' => 'Betty',
                    'student_ln' => 'Beta',
                    'course_subject' => 'HIST',
                    'course_number' => '100',
                    'grade' => '68',
                    'points' => '1',
                    'course' => NULL,
                    'student' => NULL,
                ],
                2 => [
                    'student_fn' => 'Betty',
                    'student_ln' => 'Beta',
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'grade' => '71',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
            ],
            'courses' => [
                0 => [
                    'course_subject' => 'ENGL',
                    'course_number' => '200',
                    'title' => 'Creative Writing',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                1 => [
                    'course_subject' => 'HIST',
                    'course_number' => '100',
                    'title' => 'World History',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                2 => [
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'title' => 'Algebra',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
            ],
        ],
        2 => [
            'student_fn' => 'Clara',
            'student_ln' => 'Clark',
            'degree_type' => 'BS',
            'degree_subject' => 'MATH',
            'gpa' => [
                'student_fn' => 'Clara',
                'student_ln' => 'Clark',
                'gpa' => '2',
                'student' => NULL,
            ],
            'degree' => [
                'degree_type' => 'BS',
                'degree_subject' => 'MATH',
                'title' => 'Bachelor of Science, Mathematics',
                'students' => NULL,
            ],
            'enrollments' => [
                0 => [
                    'student_fn' => 'Clara',
                    'student_ln' => 'Clark',
                    'course_subject' => 'ENGL',
                    'course_number' => '200',
                    'grade' => '74',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
                1 => [
                    'student_fn' => 'Clara',
                    'student_ln' => 'Clark',
                    'course_subject' => 'HIST',
                    'course_number' => '200',
                    'grade' => '77',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
                2 => [
                    'student_fn' => 'Clara',
                    'student_ln' => 'Clark',
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'grade' => '71',
                    'points' => '2',
                    'course' => NULL,
                    'student' => NULL,
                ],
            ],
            'courses' => [
                0 => [
                    'course_subject' => 'ENGL',
                    'course_number' => '200',
                    'title' => 'Creative Writing',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                1 => [
                    'course_subject' => 'HIST',
                    'course_number' => '200',
                    'title' => 'US History',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
                2 => [
                    'course_subject' => 'MATH',
                    'course_number' => '100',
                    'title' => 'Algebra',
                    'enrollments' => NULL,
                    'students' => NULL,
                ],
            ],
        ],
    ];
}
