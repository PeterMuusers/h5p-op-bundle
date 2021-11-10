<?php


namespace Studit\H5PBundle\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * LibraryRepository
 *
 * This class was generated by the PhpStorm "Php Annotations" Plugin. Add your own custom
 * repository methods below.
 */

class LibraryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Library::class);
    }

    public function countContentLibrary($libraryId)
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l)')
            ->join('l.contentLibraries', 'cl')
            ->join('cl.content', 'c')
            ->where('l.id = :id')
            ->setParameter('id', $libraryId);
        return $qb->getQuery()->getSingleScalarResult();
    }
    public function findLatestLibraryVersions()
    {
        $major_versions_sql = <<< EOT
  SELECT hl.machine_name, 
         MAX(hl.major_version) AS major_version
    FROM h5p_library hl
   WHERE hl.runnable = true
GROUP BY hl.machine_name
EOT;
        $minor_versions_sql = <<< EOT
  SELECT hl2.machine_name,
         hl2.major_version,
         MAX(hl2.minor_version) AS minor_version
    FROM ({$major_versions_sql}) hl1
    JOIN h5p_library hl2
      ON hl1.machine_name = hl2.machine_name
     AND hl1.major_version = hl2.major_version
GROUP BY hl2.machine_name, hl2.major_version
EOT;
        $sql = <<< EOT
  SELECT hl4.id,
         hl4.machine_name AS machine_name,
         hl4.major_version,
         hl4.minor_version,
         hl4.title,
         hl4.patch_version,
         hl4.restricted,
         hl4.has_icon
    FROM ({$minor_versions_sql}) hl3
    JOIN h5p_library hl4
      ON hl3.machine_name = hl4.machine_name
     AND hl3.major_version = hl4.major_version
     AND hl3.minor_version = hl4.minor_version
GROUP BY hl4.machine_name, 
         hl4.major_version, 
         hl4.minor_version, 
         hl4.id, 
         hl4.title,
         hl4.patch_version,
         hl4.restricted,
         hl4.has_icon
EOT;
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($sql);
        $stmt = $stmt->execute();
        $libraryVersions = $stmt->fetchAll();
        foreach ($libraryVersions as &$libraryVersion) {
            $libraryVersion = (object)$libraryVersion;
        }
        return $libraryVersions;
    }
    public function findHasSemantics($machineName, $majorVersion, $minorVersion)
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l')
            ->where('l.machineName = :machineName and l.majorVersion = :majorVersion and l.minorVersion = :minorVersion and l.semantics is not null')
            ->setParameters(['machineName' => $machineName, 'majorVersion' => $majorVersion, 'minorVersion' => $minorVersion]);
        try {
            $library = $qb->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }
        return (object)$library;
    }
    public function findAllRunnableWithSemantics()
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.machineName as name, l.title, l.majorVersion, l.minorVersion, l.restricted, l.tutorialUrl, l.metadataSettings')
            ->where('l.runnable = 1 and l.semantics is not null')
            ->orderBy('l.title');
        $libraries = $qb->getQuery()->getResult();
        foreach ($libraries as &$library) {
            $library = (object)$library;
        }
        return $libraries;
    }
    public function findOneArrayBy($parameters)
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.machineName = :machineName and l.majorVersion = :majorVersion and l.minorVersion = :minorVersion')
            ->setParameters($parameters);
        return $qb->getQuery()->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
    public function findIdBy($machineName, $majorVersion, $minorVersion)
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.machineName = :machineName and l.majorVersion = :majorVersion and l.minorVersion = :minorVersion and l.semantics is not null')
            ->setParameters(['machineName' => $machineName, 'majorVersion' => $majorVersion, 'minorVersion' => $minorVersion]);
        try {
            return $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException $e) {
            return null;
        }
    }
    public function isPatched($library)
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l)')
            ->where('l.machineName = :machineName and l.majorVersion = :majorVersion and l.minorVersion = :minorVersion and l.patchVersion < :patchVersion')
            ->setParameters(['machineName' => $library['machineName'], 'majorVersion' => $library['majorVersion'], 'minorVersion' => $library['minorVersion'], 'patchVersion' => $library['patchVersion']]);
        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}