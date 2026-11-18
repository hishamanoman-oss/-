<?php
session_start();
if(!isset($_SESSION['email'])){
    header("Location: ../Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/clients.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>clients</title>
</head>
<body>
     <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="../work.jpg" alt="profile">
                </div>
                <h2><?php echo $_SESSION['email']; ?></h2>
            </li>
            <li><a href="../index.php"><i class="fas fa-home"></i><p>dashboard</p></a></li>
            <li><a href="clients.php"><i class="fas fa-user-group"></i><p>clients</p></a></li>
            <li><a href="../Products/products.php"><i class="fas fa-table"></i><p>products</p></a></li>
            <li><a href="../charts/charts.php"><i class="fas fa-chart-pie"></i><p>charts</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>posts</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>favorite</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>settings</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>log out</p></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="title-info">
            <p>clients</p>
            <i class="fas fa-user-group"></i>
        </div>
        <div class="body">
           <form id="form" method="post">
            <div class="inpit1">
                <label for="name">name</label><br>
                <input type="text" id="name" name="name" placeholder="Enter the name" required>
            </div>
            <div class="input2">
                <label for="phone">phone</label><br>
                <input type="text" id="phone" name="phone" placeholder="Enter the Phone number" required>
            </div><br>
            <div class="input3">
                <label for="email">email</label><br>
                <input type="email" id="email" name="email" placeholder="Enter the email">   
            </div><br>
            <div>
                <button type="submit">Addition</button>
            </div>
           </form>
        </div>
        <table id="table">
            <thead>
                <tr>
                    <th>name</th>
                    <th>phone</th>
                    <th>email</th>
                    <th>procedure</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include("../db_connect.php");
                $sql = "SELECT * FROM clients ORDER BY id DESC";
                $result = mysqli_query($conn, $sql);

                if(mysqli_num_rows($result) > 0){
                    while($row = mysqli_fetch_assoc($result)){
                        echo "<tr>
                                <td>{$row['name']}</td>
                                <td>{$row['phone']}</td>
                                <td>{$row['email']}</td>
                                <td class='actions'>
                                    <button class='edit' data-id='{$row['id']}'>edit</button>
                                    <button class='delete' data-id='{$row['id']}'>delete</button>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>لا يوجد عملاء بعد</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="js/clients.js"></script>
</body>
</html>
