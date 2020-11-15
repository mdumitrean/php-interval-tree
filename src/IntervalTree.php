<?php

namespace Danon\IntervalTree;

use Iterator;

class IntervalTree
{
    public $root;
    public $nilNode;

    /**
     * Construct new empty instance of IntervalTree
     */
    public function __construct()
    {
        $this->nilNode = new Node();
    }

    /**
     * Returns number of items stored in the interval tree
     *
     * @return int
     */
    public function getSize(): int
    {
        $count = 0;
        $this->treeWalk($this->root, function () use (&$count) {
            $count++;
        });
        return $count;
    }

    /**
     * Returns array of sorted keys in the ascending order
     *
     * @return void
     */
    public function getKeys(): array
    {
        $res = [];

        $this->treeWalk($this->root, function ($node) use (&$res) {
            $res[] = ($node->item->key ? $node->item->key->output() : $node->item->key);
        });
        return $res;
    }

    /**
     * Return array of values in the ascending keys order
     * @return array
     */
    public function getValues(): array
    {
        $res = [];
        $this->treeWalk($this->root, function ($node) use (&$res) {
            $res[] = $node->item->value;
        });
        return $res;
    }

    /**
     * Returns array of items (<key,value> pairs) in the ascended keys order
     *
     * @return array
     */
    public function getItems(): array
    {
        $res = [];
        $this->treeWalk($this->root, function ($node) use (&$res) {
            $res[] = (object) [
                'key' => $node->item->key ? $node->item->key->output() : $node->item->key,
                'value' => $node->item->value,
            ];
        });
        return $res;
    }

    /**
     * Returns true if tree is empty
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return ($this->root === null || $this->root === $this->nilNode);
    }

    /**
     * Iterator of nodes which keys intersect with given interval
     * If no values stored in the tree, returns array of keys which intersect given interval
     * @param array $interval
     * @return Iterator
     */
    public function iterateIntersections(array $interval): Iterator
    {
        $searchNode = new Node($interval);
        yield from $this->treeSearchInterval($this->root, $searchNode);
    }

    /**
     * Check that interval has intersections
     *
     * @param array $interval
     * @return boolean
     */
    public function hasIntersection(array $interval): bool
    {
        $nodesIterator = $this->iterateIntersections($interval);
        return $nodesIterator->current() !== null;
    }

    /**
     * Count intervals that has intersections
     *
     * @param array $interval
     * @return boolean
     */
    public function countIntersections($interval): int
    {
        $nodesIterator = $this->iterateIntersections($interval);
        return iterator_count($nodesIterator);
    }

    /**
     * Insert new item into interval tree
     *
     * @param array $key array of two numbers [low, high]
     * @param mixed $value value representing any object (optional)
     * @return Node returns reference to inserted node
     */
    public function insert(array $key, $value = null)
    {
        $insertNode = new Node($key, $value);
        $insertNode->left = $this->nilNode;
        $insertNode->right = $this->nilNode;
        $insertNode->parent = null;
        $insertNode->color = Node::COLOR_RED;
        
        $this->treeInsert($insertNode);
        $this->recalculateMax($insertNode);
        return $insertNode;
    }


    /**
     * Remove node by key and value from the tree
     * @param Interval $key interval correspondent to keys stored in the tree
     * @param mixed $value value object
     * @return bool true if item {key, value} deleted, false if not found
     */
    public function remove(Interval $key, $value): bool
    {
        $searchNode = new Node($key, $value);
        $deleteNode = $this->treeSearch($this->root, $searchNode);
        if ($deleteNode) {
            $this->treeDelete($deleteNode);
            return true;
        }

        return false;
    }

    public function recalculateMax(Node $node)
    {
        $nodeCurrent = $node;
        while ($nodeCurrent->parent !== null) {
            $nodeCurrent->parent->updateMax();
            $nodeCurrent = $nodeCurrent->parent;
        }
    }

    public function treeInsert($insertNode)
    {
        $currentNode = $this->root;
        $parentNode = null;

        if ($this->root === null || $this->root === $this->nilNode) {
            $this->root = $insertNode;
        } else {
            while ($currentNode !== $this->nilNode) {
                $parentNode = $currentNode;
                if ($insertNode->lessThan($currentNode)) {
                    $currentNode = $currentNode->left;
                } else {
                    $currentNode = $currentNode->right;
                }
            }

            $insertNode->parent = $parentNode;

            if ($insertNode->lessThan($parentNode)) {
                $parentNode->left = $insertNode;
            } else {
                $parentNode->right = $insertNode;
            }
        }

        $this->insertFixup($insertNode);
    }

    // After insertion insert_node may have red-colored parent, and this is a single possible violation
    // Go upwards to the root and re-color until violation will be resolved
    public function insertFixup($insertNode)
    {
        $currentNode = null;
        $uncleNode = null;

        $currentNode = $insertNode;
        while ($currentNode !== $this->root && $currentNode->parent->color === Node::COLOR_RED) {
            if ($currentNode->parent === $currentNode->parent->parent->left) { // parent is left child of grandfather
                $uncleNode = $currentNode->parent->parent->right; // right brother of parent
                if ($uncleNode->color === Node::COLOR_RED) { // Case 1. Uncle is red
                    // re-color father and uncle into black
                    $currentNode->parent->color = Node::COLOR_BLACK;
                    $uncleNode->color = Node::COLOR_BLACK;
                    $currentNode->parent->parent->color = Node::COLOR_RED;
                    $currentNode = $currentNode->parent->parent;
                } else { // Case 2 & 3. Uncle is black
                    if ($currentNode === $currentNode->parent->right) { // Case 2. Current if right child
                        // This case is transformed into Case 3.
                        $currentNode = $currentNode->parent;
                        $this->rotateLeft($currentNode);
                    }
                    $currentNode->parent->color = Node::COLOR_BLACK; // Case 3. Current is left child.
                    // Re-color father and grandfather, rotate grandfather right
                    $currentNode->parent->parent->color = Node::COLOR_RED;
                    $this->rotateRight($currentNode->parent->parent);
                }
            } else { // parent is right child of grandfather
                $uncleNode = $currentNode->parent->parent->left; // left brother of parent
                if ($uncleNode->color === Node::COLOR_RED) { // Case 4. Uncle is red
                    // re-color father and uncle into black
                    $currentNode->parent->color = Node::COLOR_BLACK;
                    $uncleNode->color = Node::COLOR_BLACK;
                    $currentNode->parent->parent->color = Node::COLOR_RED;
                    $currentNode = $currentNode->parent->parent;
                } else {
                    if ($currentNode === $currentNode->parent->left) { // Case 5. Current is left child
                        // Transform into case 6
                        $currentNode = $currentNode->parent;
                        $this->rotateRight($currentNode);
                    }
                    $currentNode->parent->color = Node::COLOR_BLACK; // Case 6. Current is right child.
                    // Re-color father and grandfather, rotate grandfather left
                    $currentNode->parent->parent->color = Node::COLOR_RED;
                    $this->rotateLeft($currentNode->parent->parent);
                }
            }
        }

        $this->root->color = Node::COLOR_BLACK;
    }

    public function treeDelete($deleteNode)
    {
        $cutNode = null; // node to be cut - either delete_node or successor_node  ("y" from 14.4)
        $fixNode = null; // node to fix rb tree property   ("x" from 14.4)

        if ($deleteNode->left === $this->nilNode || $deleteNode->right === $this->nilNode) { // delete_node has less then 2 children
            $cutNode = $deleteNode;
        } else { // delete_node has 2 children
            $cutNode = $this->treeSuccessor($deleteNode);
        }

        // fix_node if single child of cut_node
        if ($cutNode->left !== $this->nilNode) {
            $fixNode = $cutNode->left;
        } else {
            $fixNode = $cutNode->right;
        }

        $fixNode->parent = $cutNode->parent;

        if ($cutNode === $this->root) {
            $this->root = $fixNode;
        } else {
            if ($cutNode === $cutNode->parent->left) {
                $cutNode->parent->left = $fixNode;
            } else {
                $cutNode->parent->right = $fixNode;
            }
            $cutNode->parent->updateMax(); // update max property of the parent
        }

        $this->recalculateMax($fixNode); // update max property upward from fix_node to root

        // deleteNode becomes cutNode, it means that we cannot hold reference
        // to node in outer structure and we will have to delete by key, additional search need
        if ($cutNode !== $deleteNode) {
            $deleteNode->copyData($cutNode);
            $deleteNode->updateMax(); // update max property of the cut node at the new place
            $this->recalculateMax($deleteNode); // update max property upward from deleteNode to root
        }

        if ( /*fix_node !== this.nil_node && */$cutNode->color === Node::COLOR_BLACK) {
            $this->deleteFixup($fixNode);
        }
    }

    public function deleteFixup($fixNode)
    {
        $currentNode = $fixNode;

        while ($currentNode !== $this->root && $currentNode->parent !== null && $currentNode->color === Node::COLOR_BLACK) {
            if ($currentNode === $currentNode->parent->left) { // fix node is left child
                $brotherNode = $currentNode->parent->right;
                if ($brotherNode->color === Node::COLOR_RED) { // Case 1. Brother is red
                    $brotherNode->color = Node::COLOR_BLACK; // re-color brother
                    $currentNode->parent->color = Node::COLOR_RED; // re-color father
                    $this->rotateLeft($currentNode->parent);
                    $brotherNode = $currentNode->parent->right; // update brother
                }
                // Derive to cases 2..4: brother is black
                if (
                    $brotherNode->left->color === Node::COLOR_BLACK &&
                    $brotherNode->right->color === Node::COLOR_BLACK
                ) { // case 2: both nephews black
                    $brotherNode->color = Node::COLOR_RED; // re-color brother
                    $currentNode = $currentNode->parent; // continue iteration
                } else {
                    if ($brotherNode->right->color === Node::COLOR_BLACK) { // case 3: left nephew red, right nephew black
                        $brotherNode->color = Node::COLOR_RED; // re-color brother
                        $brotherNode->left->color = Node::COLOR_BLACK; // re-color nephew
                        $this->rotateRight($brotherNode);
                        $brotherNode = $currentNode->parent->right; // update brother
                        // Derive to case 4: left nephew black, right nephew red
                    }
                    // case 4: left nephew black, right nephew red
                    $brotherNode->color = $currentNode->parent->color;
                    $currentNode->parent->color = Node::COLOR_BLACK;
                    $brotherNode->right->color = Node::COLOR_BLACK;
                    $this->rotateLeft($currentNode->parent);
                    $currentNode = $this->root; // exit from loop
                }
            } else { // fix node is right child
                $brotherNode = $currentNode->parent->left;
                if ($brotherNode->color === Node::COLOR_RED) { // Case 1. Brother is red
                    $brotherNode->color = Node::COLOR_BLACK; // re-color brother
                    $currentNode->parent->color = Node::COLOR_RED; // re-color father
                    $this->rotateRight($currentNode->parent);
                    $brotherNode = $currentNode->parent->left; // update brother
                }
                // Go to cases 2..4
                if (
                    $brotherNode->left->color === Node::COLOR_BLACK &&
                    $brotherNode->right->color === Node::COLOR_BLACK
                ) { // case 2
                    $brotherNode->color = Node::COLOR_RED; // re-color brother
                    $currentNode = $currentNode->parent; // continue iteration
                } else {
                    if ($brotherNode->left->color === Node::COLOR_BLACK) { // case 3: right nephew red, left nephew black
                        $brotherNode->color = Node::COLOR_RED; // re-color brother
                        $brotherNode->right->color = Node::COLOR_BLACK; // re-color nephew
                        $this->rotateLeft($brotherNode);
                        $brotherNode = $currentNode->parent->left; // update brother
                        // Derive to case 4: right nephew black, left nephew red
                    }
                    // case 4: right nephew black, left nephew red
                    $brotherNode->color = $currentNode->parent->color;
                    $currentNode->parent->color = Node::COLOR_BLACK;
                    $brotherNode->left->color = Node::COLOR_BLACK;
                    $this->rotateRight($currentNode->parent);
                    $currentNode = $this->root; // force exit from loop
                }
            }
        }

        $currentNode->color = Node::COLOR_BLACK;
    }

    public function treeSearch($node, $searchNode)
    {
        if ($node === null || $node === $this->nilNode) {
            return null;
        }

        if ($searchNode->equalTo($node)) {
            return $node;
        }
        if ($searchNode->lessThan($node)) {
            return $this->treeSearch($node->left, $searchNode);
        } else {
            return $this->treeSearch($node->right, $searchNode);
        }
    }

    // Original search_interval method; container res support push() insertion
    // Search all intervals intersecting given one
    public function treeSearchInterval($node, $searchNode, &$res = [])
    {
        if ($node !== null && $node !== $this->nilNode) {
            // if (node->left !== this.nil_node && node->left->max >= low) {
            if ($node->left !== $this->nilNode && !$node->notIntersectLeftSubtree($searchNode)) {
                yield from $this->treeSearchInterval($node->left, $searchNode, $res);
            }
            // if (low <= node->high && node->low <= high) {
            if ($node->intersect($searchNode)) {
                $res[] = $node;
                yield $node;
            }
            // if (node->right !== this.nil_node && node->low <= high) {
            if ($node->right !== $this->nilNode && !$node->notIntersectRightSubtree($searchNode)) {
                yield from $this->treeSearchInterval($node->right, $searchNode, $res);
            }
        }
    }

    public function localMinimum(Node $node): Node
    {
        $nodeMin = $node;
        while ($nodeMin->left !== null && $nodeMin->left !== $this->nilNode) {
            $nodeMin = $nodeMin->left;
        }
        return $nodeMin;
    }

    private function treeSuccessor(Node $node)
    {
        if ($node->right !== $this->nilNode) {
            $nodeSuccessor = $this->localMinimum($node->right);
        } else {
            $currentNode = $node;
            $parentNode = $node->parent;
            while ($parentNode !== null && $parentNode->right === $currentNode) {
                $currentNode = $parentNode;
                $parentNode = $parentNode->parent;
            }
            $nodeSuccessor = $parentNode;
        }
        return $nodeSuccessor;
    }


    /**
     * General left rotation
     *
     * @param Node $x top or rotated subtree
     */
    private function rotateLeft(Node $x)
    {
        $y = $x->right;

        $x->right = $y->left; // b goes to x.right

        if ($y->left !== $this->nilNode) {
            $y->left->parent = $x; // x becomes parent of b
        }
        $y->parent = $x->parent; // move parent

        if ($x === $this->root) {
            $this->root = $y; // y becomes root
        } else { // y becomes child of x.parent
            if ($x === $x->parent->left) {
                $x->parent->left = $y;
            } else {
                $x->parent->right = $y;
            }
        }
        $y->left = $x; // x becomes left child of y
        $x->parent = $y; // and y becomes parent of x

        if ($x !== null && $x !== $this->nilNode) {
            $x->updateMax();
        }

        $y = $x->parent;
        if ($y !== null && $y !== $this->nilNode) {
            $y->updateMax();
        }
    }

    /**
     * General right rotation
     *
     * @param Node $y top of rotated subtree
     */
    private function rotateRight(Node $y)
    {
        $x = $y->left;

        $y->left = $x->right; // b goes to y.left

        if ($x->right !== $this->nilNode) {
            $x->right->parent = $y; // y becomes parent of b
        }
        $x->parent = $y->parent; // move parent

        if ($y === $this->root) { // x becomes root
            $this->root = $x;
        } else { // y becomes child of x.parent
            if ($y === $y->parent->left) {
                $y->parent->left = $x;
            } else {
                $y->parent->right = $x;
            }
        }
        $x->right = $y; // y becomes right child of x
        $y->parent = $x; // and x becomes parent of y

        if ($y !== null && $y !== $this->nilNode) {
            $y->updateMax();
        }

        $x = $y->parent;
        if ($x !== null && $x !== $this->nilNode) {
            $y->updateMax();
        }
    }

    public function treeWalk($node, $action)
    {
        if ($node !== null && $node !== $this->nilNode) {
            $this->treeWalk($node->left, $action);
            // arr.push(node.toArray());
            $action($node);
            $this->treeWalk($node->right, $action);
        }
    }
};
