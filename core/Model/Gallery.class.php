<?php

/**
 * @copyright   Nick Espig <nickespig.xyz>
 * @author      Nick Espig <info@nickespig.xyz>
 * @package     imagely
 * @version     1.0
 * @subpackage  core
 */
class Gallery
{

    /**
     * Get all information from all galleries sorted by creationDate
     *
     * @return array - Array of all galleries with these information: [n] - id, author, status, name, description,
     *               createDate and modifiedData
     */
    function getAll()
    {
        $sth = $GLOBALS['db']->prepare('SELECT * FROM gallery ORDER BY creationDate DESC');
        $sth->execute();
        $result = $sth->fetchAll();
        $return = json_decode(json_encode($result), TRUE);


        for ($i = 0 ; $i < count($return) ; $i++) {
            $return[ $i ]['description'] = html_entity_decode($return[ $i ]['description']);

            $date = new DateTime();
            $date->setTimestamp($return[ $i ]['creationDate']);
            $return[ $i ]['creationDate'] = $date->format('d.m.Y H:i');
        }


        return $return;
    }

    /**
     * Get all galleries from a specific user by it's id sorted by creationDate
     *
     * @param int $userID - Authors ID
     *
     * @return array - Array of all galleries from the given userID with the following data: [n] - id, author, status,
     *               name, description, createDate and modifiedData
     */
    function getAllByAuthor($userID)
    {
        $sth = $GLOBALS['db']->prepare('SELECT * FROM gallery WHERE author=' . $userID . ' ORDER BY creationDate DESC');
        $sth->execute();
        $result = $sth->fetchAll();
        $return = json_decode(json_encode($result), TRUE);

        return $return;
    }

    /**
     * Gets all ID's of all galleries
     *
     * @return array - Array with all galleryID's
     */
    function getAvailableGalleries()
    {
        $sth = $GLOBALS['db']->prepare('SELECT id FROM gallery');
        $sth->execute();
        $result = $sth->fetchAll();
        $return = json_decode(json_encode($result), TRUE);

        return $return;
    }

    /**
     * Create a gallery
     *
     * @param array $data - Data of the gallery must contain these keys: author : int - the authors userID, status :
     *                    boolean - the status of the gallery, name : string - Name of the gallery, description :
     *                    string - description of the gallery , creationDate : string and modifiedDate : string - the
     *                    las two as formatted date (this will be changed to timestamp)
     */
    function createGallery($data)
    {
        $this->checkImage($data['image']);

        $author       = $data['author'];
        $status       = $data['status'];
        $name         = htmlentities($data['name']);
        $description  = htmlentities($data['description']);
        $creationDate = $data['creationDate'];
        $modifiedDate = $data['modifiedDate'];

        $teaserImage = $this->createTeaserImage($data['image']);

        $teaserImagePath      = $teaserImage['imagePath'];
        $teaserImageThumbnail = $teaserImage['imageThumbnailPath'];

        $sth      = $GLOBALS['db']->prepare('INSERT INTO gallery (author,status,name,description,creationDate,modifiedDate,teaserImage,teaserImageThumbnail1,teaserImageThumbnail2,teaserImageThumbnail3) VALUES (:author,:status,:name,:description,:creationDate,:modifiedDate,:teaserImage,:teaserImageThumbnail1,:teaserImageThumbnail2,:teaserImageThumbnail3)');
        $bindings = [
            ':author'                => $author,
            ':status'                => $status,
            ':name'                  => $name,
            ':description'           => $description,
            ':creationDate'          => $creationDate,
            ':modifiedDate'          => $modifiedDate,
            ':teaserImage'           => $teaserImagePath,
            ':teaserImageThumbnail1' => $teaserImageThumbnail['small'],
            ':teaserImageThumbnail2' => $teaserImageThumbnail['medium'],
            ':teaserImageThumbnail3' => $teaserImageThumbnail['large'],
        ];
        $sth->execute($bindings);

    }

    /**
     * creates TeaseImage and the gallery folder, as well as the thumbnails
     *
     * @param string $image - Path to the image
     *
     * @return array - Array with the teaser image path and the thumbnail paths
     *               ['imagePath'] => teaserImgPath, ['imageThumbnailPath] => ['small'] => path, ['medium'] => path,
     *               ['large'] => path
     * @throws Exception - If the given file is not an image
     */
    private function createTeaserImage($image)
    {
        $info = getimagesize($image['tmp_name']);
        if ($info[2] == IMAGETYPE_GIF)
            throw new Exception('Teaser image can\'t be a GIF, it has to be a JPEG or a PNG!');

        $galleryID = $this->getNextGalleryId();
        $userID    = $_SESSION['userId'];

        $folderPath = '/data/media/gallery/' . $userID . DIRECTORY_SEPARATOR . $galleryID . DIRECTORY_SEPARATOR;
        $imageName  = 'teaserImage';

        $finalImagePaht = $folderPath . $imageName . '.png';
        if (!is_dir(DOCUMENT_ROOT . $folderPath))
            mkdir(DOCUMENT_ROOT . $folderPath, 0755, TRUE);

        imagepng(imagecreatefromstring(file_get_contents($image['tmp_name'])), DOCUMENT_ROOT . $finalImagePaht);
        $thumnail = $this->createThumbnail($finalImagePaht, $imageName, $folderPath);

        $result = [
            'imagePath'          => $finalImagePaht,
            'imageThumbnailPath' => $thumnail,
        ];

        return $result;
    }

    /**
     * Checks if a file is an image (gif/jpeg/png)
     *
     * @param string $image - Path to file
     *
     * @return bool - If it is an image
     * @throws Exception - If it's not an image
     */
    private function checkImage($image)
    {
        $info = getimagesize($image['tmp_name']);
        if ($info === FALSE) {
            throw new Exception("Unable to determine image type of uploaded file");
        }


        if (($info[2] !== IMAGETYPE_GIF) && ($info[2] !== IMAGETYPE_JPEG) && ($info[2] !== IMAGETYPE_PNG)) {
            throw new Exception("Not a gif/jpeg/png");
        }

        return TRUE;
    }

    /**
     * Generates 3 different sizes of thumbnails 100px, 200px & 400px height
     *
     * @param string $image - Path to picture from which the thumbs should be created
     * @param string $name  - name of hte picture
     * @param string $path  - path to save the pictures
     *
     * @return array - Array with the 3 different sizes of thumbnails:
     *               small => path, medium => path, large => path
     */
    private function createThumbnail($image, $name, $path)
    {
        $result = [];

        $imageInfo = getimagesize(DOCUMENT_ROOT . $image);
        $width     = $imageInfo[0];
        $height    = $imageInfo[1];

        $new_height = [
            'small'  => 100,
            'medium' => 200,
            'large'  => 400,
        ];
        foreach ($new_height as $sizeName => $new_thumb_height) {
            $thumbname = $name . '.' . $sizeName . '.thumb.png';
            $new_width = floor($width * ($new_thumb_height / $height));

            list(, , $type) = $imageInfo;

            $type               = image_type_to_extension($type);
            $getResourceOfImage = 'imagecreatefrom' . $type;
            $getResourceOfImage = str_replace('.', '', $getResourceOfImage);
            $img                = $getResourceOfImage(DOCUMENT_ROOT . $image);
            $tmp_img            = imagecreatetruecolor($new_width, $new_thumb_height);
            imagecopyresized($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_thumb_height, $width, $height);

            $new_path = DOCUMENT_ROOT . $path;
            imagepng($tmp_img, "{$new_path}{$thumbname}");

            $result[ $sizeName ] = $path . $thumbname;
        }

        return $result;
    }

    /**
     * Gets the number of the next gallery which will be created
     *
     * @return int - Number of next gallery
     */
    private function getNextGalleryId()
    {
        $sth = $GLOBALS['db']->prepare('SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = \'gallery\' AND table_schema = \'imagely\' ');
        $sth->execute();

        $result = json_decode(json_encode($sth->fetch()), TRUE);

        return (int)$result['AUTO_INCREMENT'];
    }

    /**
     * Updates a gallery
     *
     * @param array $request - New gallery data  must contain: id : int, status : boolean, name : string, description :
     *                       string and modifiedDate : string (will be change to timestamp in the future)
     */
    function editGallery($request)
    {
        $id           = htmlentities($request['id']);
        $status       = htmlentities($request['status']);
        $name         = htmlentities($request['name']);
        $description  = htmlentities($request['description']);
        $modifiedDate = $request['modifiedDate'];
        $sth          = $GLOBALS['db']->prepare('UPDATE gallery SET status=\'' . $status . '\', name=\'' . $name . '\', description=\'' . $description . '\', modifiedDate=\'' . $modifiedDate . '\' WHERE id=' . $id);
        $sth->execute();
    }

    /**
     * Deletes all galleries from a user
     *
     * @param int $userID - Users ID
     */
    function deleteGalleryByUser($userID)
    {
        $sth = $GLOBALS['db']->prepare('SELECT id FROM gallery WHERE author=' . $userID);
        $sth->execute();
        while ($result = json_decode(json_encode($sth->fetchAll()), TRUE)) {
            Gallery::deleteGalleryById($result['id']);
        }
    }

    /**
     * Deletes Gallery by it's ID
     *
     * @param int $id - The Gallery's ID
     */
    function deleteGalleryById($id)
    {
        $sth = $GLOBALS['db']->prepare('DELETE FROM gallery WHERE id=' . $id);
        $sth->execute();
    }

    /**
     * Gets all information from a specific gallery by it's ID
     *
     * @param int $id - Gallery ID
     *
     * @return mixed - An Array with this data: id, author - ID of the user, status, name, description, creationDate,
     *               modifiedDate
     */
    function getGalleryById($id)
    {
        $sth = $GLOBALS['db']->prepare('SELECT * FROM gallery WHERE id=' . $id);
        $sth->execute();
        $return                = json_decode(json_encode($sth->fetch()), TRUE);
        $return['description'] = html_entity_decode($return['description']);

        $date = new DateTime();
        $date->setTimestamp($return['creationDate']);
        $return['creationDate'] = $date->format('d-m-Y H:i');

        return $return;
    }

    /**
     * Gets all images from a gallery
     *
     * @param int $galleryID - the galleries ID
     *
     * @return mixed - An Array with the following data: [n] - id, galleryId, imagePath, thumbnailPath, thumbnailPath2
     *               and thumbnailPath3
     */
    function getImageByGalleryId($galleryID)
    {
        $sth = $GLOBALS['db']->prepare('SELECT * FROM image WHERE galleryId=' . $galleryID);
        $sth->execute();
        $return = json_decode(json_encode($sth->fetchAll()), TRUE);

        return $return;
    }

    /**
     * Check if given user id matches with the given id or if the user is an admin, otherwise it will forward the user
     * to the given url
     *
     * @param int $userID      - The users ID
     * @param int $id          - ID you want to compare
     * @param int $redirectURL - URL to redirect to
     *
     * @return bool
     */
    function checkIfOwnAccountOrRedirect($userID, $id, $redirectURL)
    {
        $return = [];
        $sth    = $GLOBALS['db']->prepare('SELECT id FROM gallery WHERE author=' . $userID);
        $sth->execute();
        while ($result = json_decode(json_encode($sth->fetch()), TRUE)) {
            $return[] = (string)$result['id'];
        }
        if (in_array($id, $return, TRUE) || User::isAdmin($userID) == 1) {
            return TRUE;
        }
        header($redirectURL);

        return FALSE;
    }

    /**
     * Get average luminance of an image to determine if it's dark or light
     * Function from here: http://stackoverflow.com/a/5959461/5699307
     *
     * @param string $filename    - FilePath
     * @param int    $num_samples - Get average luminance, by sampling $num_samples times in both x,y directions
     *
     * @return float - Average luminance of the image
     */
    public function get_avg_luminance($filename, $num_samples = 10)
    {
        $img = imagecreatefrompng(DOCUMENT_ROOT . $filename);

        $width  = imagesx($img);
        $height = imagesy($img);

        $x_step = intval($width / $num_samples);
        $y_step = intval($height / $num_samples);

        $total_lum = 0;

        $sample_no = 1;

        for ($x = 0 ; $x < $width ; $x += $x_step) {
            for ($y = 0 ; $y < $height ; $y += $y_step) {

                $rgb = imagecolorat($img, $x, $y);
                $r   = ($rgb >> 16) & 0xFF;
                $g   = ($rgb >> 8) & 0xFF;
                $b   = $rgb & 0xFF;

                // choose a simple luminance formula from here
                // http://stackoverflow.com/questions/596216/formula-to-determine-brightness-of-rgb-color
                $lum = ($r + $r + $b + $g + $g + $g) / 6;

                $total_lum += $lum;

                // debugging code
                //           echo "$sample_no - XY: $x,$y = $r, $g, $b = $lum<br />";
                $sample_no++;
            }
        }

        // work out the average
        $avg_lum = $total_lum / $sample_no;

        return $avg_lum;
    }


}
