?>
<?php
// include/wishlist_counter.php
function getWishlistCount($user_id) {
    global $conn;

    $query = "SELECT COUNT(*) as count FROM wishlist WHERE fk_utente = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();

    return $count;
}

// Da includere nell'header
if (SessionManager::isLoggedIn()) {
    $wishlist_count = getWishlistCount(SessionManager::getUserId());
}
?>

<!-- Nel file header.php, aggiungere questo link -->
<a href="<?php echo BASE_URL; ?>/pages/wishlist.php" class="nav-link position-relative" title="Wishlist">
    <i class="bi bi-heart-fill"></i>
    <?php if (isset($wishlist_count) && $wishlist_count > 0): ?>
        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
            <?php echo $wishlist_count; ?>
        </span>
    <?php endif; ?>
</a>